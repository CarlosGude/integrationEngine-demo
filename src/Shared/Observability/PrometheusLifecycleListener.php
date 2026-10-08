<?php

declare(strict_types=1);

namespace App\Shared\Observability;

use IntegrationEngine\Core\Event\RequestFailed;
use IntegrationEngine\Core\Event\ResponseMapped;
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

    /** @var list<string> */
    private const LABEL_NAMES = ['integration', 'action', 'status_class'];

    public function __construct(
        private CollectorRegistry $registry,
        private LoggerInterface $logger,
    ) {
    }

    #[AsEventListener(event: ResponseMapped::class)]
    public function onResponseMapped(ResponseMapped $event): void
    {
        $labels = $this->labels($event->integrationName, $event->action, $event->statusCode);

        $this->record(function () use ($labels, $event): void {
            $this->requestsTotal()->inc($labels);
            $this->durationMs()->observe($event->durationMs, $labels);
        });
    }

    #[AsEventListener(event: RequestFailed::class)]
    public function onRequestFailed(RequestFailed $event): void
    {
        $labels = $this->labels($event->integrationName, $event->action, $event->statusCode);

        $this->record(function () use ($labels, $event): void {
            $this->requestsTotal()->inc($labels);
            $this->failuresTotal()->inc($labels);
            $this->durationMs()->observe($event->durationMs, $labels);
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
}
