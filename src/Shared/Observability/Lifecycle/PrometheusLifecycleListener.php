<?php

declare(strict_types=1);

namespace App\Shared\Observability\Lifecycle;

use App\Shared\Observability\StatusClass;
use IntegrationEngine\Core\Event\RequestFailed;
use IntegrationEngine\Core\Event\ResponseMapped;
use IntegrationEngine\Core\Event\ResponseReceived;
use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Histogram;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Turns IntegrationEngine's lifecycle events into Prometheus metrics, without
 * the bundle or any integration knowing this listener exists.
 */
final readonly class PrometheusLifecycleListener
{
    private const NAMESPACE = 'integration';

    /** @var list<float> */
    private const DURATION_BUCKETS = [50.0, 100.0, 250.0, 500.0, 1000.0, 2000.0, 5000.0];

    /**
     * Mapping runs in-process on an already-decoded body, so it lives in a
     * range two or three orders of magnitude below the request itself.
     *
     * @var list<float>
     */
    private const MAPPING_BUCKETS = [0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0, 25.0, 50.0];

    /** @var list<string> */
    private const LABEL_NAMES = ['integration', 'action', 'status_class'];

    private ReceivedResponses $received;

    public function __construct(
        private CollectorRegistry $registry,
        private LoggerInterface $logger,
    ) {
        $this->received = new ReceivedResponses();
    }

    #[AsEventListener(event: ResponseReceived::class)]
    public function onResponseReceived(ResponseReceived $event): void
    {
        $this->received->remember($event);
    }

    #[AsEventListener(event: ResponseMapped::class)]
    public function onResponseMapped(ResponseMapped $event): void
    {
        $received = $this->received->take($event->integrationName, $event->action, $event->requestKey);
        $labels = $this->labels($event->integrationName, $event->action, $event->statusCode);

        $this->record(function () use ($labels, $event, $received): void {
            $this->requestsTotal()->inc($labels);
            $this->durationMs()->observe($event->durationMs, $labels);

            if ($received !== null) {
                $this->mappingDurationMs()->observe(ReceivedResponses::mappingMs($received, $event->timestamp), $labels);
            }
        });
    }

    #[AsEventListener(event: RequestFailed::class)]
    public function onRequestFailed(RequestFailed $event): void
    {
        $received = $this->received->take($event->integrationName, $event->action, $event->requestKey);
        $labels = $this->labels($event->integrationName, $event->action, $event->statusCode);

        $this->record(function () use ($labels, $event, $received): void {
            $this->requestsTotal()->inc($labels);
            $this->failuresTotal()->inc($labels);
            $this->durationMs()->observe($event->durationMs, $labels);

            // ResponseReceived followed by RequestFailed: the API answered
            // and the mapper threw, which usually means the response shape
            // changed under us rather than the upstream being down. The
            // failure itself carries status 0, so label it with the status
            // the API actually answered with.
            if ($received !== null) {
                $this->mappingFailuresTotal()->inc(
                    $this->labels($event->integrationName, $event->action, $received->statusCode),
                );
            }
        });
    }

    /**
     * @return list<string>
     */
    private function labels(string $integrationName, string $action, int $statusCode): array
    {
        // Labels are limited to these three on purpose. requestKey, message,
        // exceptionClass and responseClass are per-request or unbounded
        // values: attaching any of them would make Prometheus allocate a new
        // time series per request instead of per (integration, action,
        // status class), which is a cardinality explosion a scrape-based
        // system never recovers from.
        return [$integrationName, $action, StatusClass::fromCode($statusCode)];
    }

    private function record(callable $operation): void
    {
        try {
            $operation();
        } catch (\Throwable $e) {
            $this->logger->warning('Failed to record a Prometheus metric from an integration lifecycle event.', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
            ]);
        }
    }

    private function requestsTotal(): Counter
    {
        return $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'requests_total',
            'Total number of integration requests.',
            self::LABEL_NAMES,
        );
    }

    private function failuresTotal(): Counter
    {
        return $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'failures_total',
            'Total number of failed integration requests.',
            self::LABEL_NAMES,
        );
    }

    private function durationMs(): Histogram
    {
        return $this->registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'duration_ms',
            'Integration request duration in milliseconds.',
            self::LABEL_NAMES,
            self::DURATION_BUCKETS,
        );
    }

    private function mappingFailuresTotal(): Counter
    {
        return $this->registry->getOrRegisterCounter(
            self::NAMESPACE,
            'mapping_failures_total',
            'Total number of integration responses that arrived but failed to map.',
            self::LABEL_NAMES,
        );
    }

    private function mappingDurationMs(): Histogram
    {
        return $this->registry->getOrRegisterHistogram(
            self::NAMESPACE,
            'mapping_duration_ms',
            'Time from a received integration response to its mapped response, in milliseconds.',
            self::LABEL_NAMES,
            self::MAPPING_BUCKETS,
        );
    }
}
