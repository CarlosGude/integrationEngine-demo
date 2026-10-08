<?php

declare(strict_types=1);

namespace Tests\Shared\Observability\Lifecycle;

use App\Shared\Observability\Lifecycle\PrometheusLifecycleListener;
use IntegrationEngine\Core\Event\RequestFailed;
use IntegrationEngine\Core\Event\ResponseMapped;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\Sample;
use Prometheus\Storage\InMemory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class PrometheusLifecycleListenerTest extends TestCase
{
    #[Test]
    public function responseMappedIncrementsRequestsTotalAndObservesDuration(): void
    {
        $registry = new CollectorRegistry(new InMemory(), registerDefaultMetrics: false);
        $listener = new PrometheusLifecycleListener($registry, new NullLogger());

        $listener->onResponseMapped(new ResponseMapped(
            integrationName: 'tmdb',
            action: 'get_movie',
            durationMs: 42.5,
            statusCode: 200,
            responseClass: 'App\Integrations\Tmdb\GetMovie\GetMovieResponse',
            timestamp: microtime(true),
            requestKey: 'req-123',
        ));

        $requestSamples = $this->samplesFor($registry, 'integration_requests_total');
        self::assertCount(1, $requestSamples);
        self::assertSame('1', $requestSamples[0]->getValue());
        self::assertSame(['tmdb', 'get_movie', '2xx'], $requestSamples[0]->getLabelValues());

        self::assertNotEmpty($this->samplesFor($registry, 'integration_duration_ms'));
        self::assertEmpty($this->samplesFor($registry, 'integration_failures_total'));
    }

    #[Test]
    public function requestFailedIncrementsBothRequestsAndFailuresTotal(): void
    {
        $registry = new CollectorRegistry(new InMemory(), registerDefaultMetrics: false);
        $listener = new PrometheusLifecycleListener($registry, new NullLogger());

        $listener->onRequestFailed(new RequestFailed(
            integrationName: 'supplier',
            action: 'get_prices',
            durationMs: 15.0,
            statusCode: 503,
            exceptionClass: 'RuntimeException',
            message: 'Connection refused',
            timestamp: microtime(true),
        ));

        $requestSamples = $this->samplesFor($registry, 'integration_requests_total');
        $failureSamples = $this->samplesFor($registry, 'integration_failures_total');

        self::assertCount(1, $requestSamples);
        self::assertCount(1, $failureSamples);
        self::assertSame(['supplier', 'get_prices', '5xx'], $failureSamples[0]->getLabelValues());
        self::assertNotEmpty($this->samplesFor($registry, 'integration_duration_ms'));
    }

    #[Test]
    public function registeredLabelsAreExactlyIntegrationActionAndStatusClass(): void
    {
        $registry = new CollectorRegistry(new InMemory(), registerDefaultMetrics: false);
        $listener = new PrometheusLifecycleListener($registry, new NullLogger());

        $listener->onResponseMapped(new ResponseMapped(
            integrationName: 'tmdb',
            action: 'get_movie',
            durationMs: 10.0,
            statusCode: 200,
            responseClass: 'App\Foo',
            timestamp: microtime(true),
            requestKey: 'should-never-become-a-label',
        ));

        $families = $registry->getMetricFamilySamples();
        self::assertNotEmpty($families);

        foreach ($families as $family) {
            self::assertSame(['integration', 'action', 'status_class'], $family->getLabelNames());
        }
    }

    #[Test]
    public function storageFailuresAreLoggedAndNeverPropagated(): void
    {
        $registry = $this->createMock(CollectorRegistry::class);
        $registry->method('getOrRegisterCounter')->willThrowException(new \RuntimeException('storage down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Failed to record a Prometheus metric from an integration lifecycle event.',
                ['exception' => \RuntimeException::class, 'message' => 'storage down'],
            );

        $listener = new PrometheusLifecycleListener($registry, $logger);

        $listener->onResponseMapped(new ResponseMapped(
            integrationName: 'tmdb',
            action: 'get_movie',
            durationMs: 10.0,
            statusCode: 200,
            responseClass: 'App\Foo',
            timestamp: microtime(true),
        ));
    }

    /**
     * @return Sample[]
     */
    private function samplesFor(CollectorRegistry $registry, string $name): array
    {
        foreach ($registry->getMetricFamilySamples() as $family) {
            if ($family->getName() === $name) {
                return $family->getSamples();
            }
        }

        return [];
    }
}
