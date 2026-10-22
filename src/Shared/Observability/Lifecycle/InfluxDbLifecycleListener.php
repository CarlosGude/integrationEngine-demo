<?php

declare(strict_types=1);

namespace App\Shared\Observability\Lifecycle;

use App\Shared\Observability\StatusClass;
use InfluxDB2\Client;
use InfluxDB2\Model\WritePrecision;
use InfluxDB2\Point;
use IntegrationEngine\Core\Event\RequestFailed;
use IntegrationEngine\Core\Event\ResponseMapped;
use IntegrationEngine\Core\Event\ResponseReceived;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\ConsoleEvents;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Pushes one point per lifecycle event to InfluxDB. The demo has no workers,
 * so points are only ever accumulated in memory during a request or console
 * command and written in one batch on terminate — never inline with the
 * event, which would otherwise hold up the response on a slow or unreachable
 * InfluxDB server.
 *
 * Disabled by default (INFLUXDB_ENABLED=0): every method below becomes a
 * no-op, so nothing is ever buffered or written.
 */
final class InfluxDbLifecycleListener
{
    private const MEASUREMENT = 'integration_request';

    /** @var list<Point> */
    private array $points = [];

    private readonly ReceivedResponses $received;

    public function __construct(
        private readonly bool $enabled,
        private readonly Client $client,
        private readonly LoggerInterface $logger,
    ) {
        $this->received = new ReceivedResponses();
    }

    #[AsEventListener(event: ResponseReceived::class)]
    public function onResponseReceived(ResponseReceived $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $this->received->remember($event);
    }

    #[AsEventListener(event: ResponseMapped::class)]
    public function onResponseMapped(ResponseMapped $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $point = $this->point(
            $event->integrationName,
            $event->action,
            $event->statusCode,
            $event->durationMs,
            $event->timestamp,
            $event->requestKey,
        );

        $received = $this->received->take($event->integrationName, $event->action, $event->requestKey);
        if ($received !== null) {
            $point->addField('mapping_ms', ReceivedResponses::mappingMs($received, $event->timestamp));
        }

        $this->points[] = $point;
    }

    #[AsEventListener(event: RequestFailed::class)]
    public function onRequestFailed(RequestFailed $event): void
    {
        if (!$this->enabled) {
            return;
        }

        $point = $this->point(
            $event->integrationName,
            $event->action,
            $event->statusCode,
            $event->durationMs,
            $event->timestamp,
            $event->requestKey,
        );

        // true when the API answered and the mapper threw (ResponseReceived
        // came first); false when the call never got a response at all.
        $received = $this->received->take($event->integrationName, $event->action, $event->requestKey);
        $point->addField('mapping_failed', $received !== null);

        $this->points[] = $point;
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    #[AsEventListener(event: ConsoleEvents::TERMINATE)]
    public function flush(): void
    {
        if (!$this->enabled || $this->points === []) {
            return;
        }

        $points = $this->points;
        $this->points = [];

        try {
            $this->client->createWriteApi()->write($points, WritePrecision::MS);
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to write integration lifecycle points to InfluxDB.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'pointCount' => \count($points),
            ]);
        }
    }

    private function point(
        string $integrationName,
        string $action,
        int $statusCode,
        float $durationMs,
        float $timestamp,
        int|string|null $requestKey,
    ): Point {
        $point = Point::measurement(self::MEASUREMENT)
            ->addTag('integration', $integrationName)
            ->addTag('action', $action)
            ->addTag('status_class', StatusClass::fromCode($statusCode))
            ->addField('duration_ms', $durationMs)
            ->addField('status_code', $statusCode)
            ->time((int) round($timestamp * 1000), WritePrecision::MS);

        // requestKey is a field here, never a tag. InfluxDB only indexes
        // tags — a field doesn't create a new series per value — which is
        // the opposite of Prometheus, where it's never a label at all (see
        // PrometheusLifecycleListener::labels()) because every label value
        // there does create a new series.
        if ($requestKey !== null) {
            $point->addField('request_key', $requestKey);
        }

        return $point;
    }
}
