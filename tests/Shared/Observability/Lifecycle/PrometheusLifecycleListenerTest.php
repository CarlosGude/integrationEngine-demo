<?php

declare(strict_types=1);

namespace Tests\Shared\Observability\Lifecycle;

use App\Shared\Observability\Lifecycle\PrometheusLifecycleListener;
use IntegrationEngine\Core\Event\RequestFailed;
use IntegrationEngine\Core\Event\ResponseMapped;
use IntegrationEngine\Core\Event\ResponseReceived;
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
    public function aReceivedThenMappedResponseObservesItsMappingTime(): void
    {
        $registry = new CollectorRegistry(new InMemory(), registerDefaultMetrics: false);
        $listener = new PrometheusLifecycleListener($registry, new NullLogger());

        $listener->onResponseReceived(new ResponseReceived('tmdb', 'get_movie', 40.0, 200, 1_700_000_000.040));
        $listener->onResponseMapped(new ResponseMapped('tmdb', 'get_movie', 42.0, 200, 'App\Foo', 1_700_000_000.0418));

        $samples = $this->samplesFor($registry, 'integration_mapping_duration_ms');
        $count = array_values(array_filter($samples, static fn (Sample $s): bool => $s->getName() === 'integration_mapping_duration_ms_count'));
        $sum = array_values(array_filter($samples, static fn (Sample $s): bool => $s->getName() === 'integration_mapping_duration_ms_sum'));

        self::assertSame('1', (string) $count[0]->getValue());
        self::assertSame(['tmdb', 'get_movie', '2xx'], $count[0]->getLabelValues());
        self::assertEqualsWithDelta(1.8, (float) $sum[0]->getValue(), 0.001);
        // 1.8ms lands in the 2.5 bucket, not the 1.0 one.
        $buckets = array_column(array_map(static fn (Sample $s): array => [
            'le' => $s->getLabelValues()[3] ?? null,
            'value' => (string) $s->getValue(),
        ], array_filter($samples, static fn (Sample $s): bool => $s->getName() === 'integration_mapping_duration_ms_bucket')), 'value', 'le');
        self::assertSame('0', $buckets['1']);
        self::assertSame('1', $buckets['2.5']);
    }

    #[Test]
    public function aMappedResponseWithoutAReceivedEventObservesNoMappingTime(): void
    {
        $registry = new CollectorRegistry(new InMemory(), registerDefaultMetrics: false);
        $listener = new PrometheusLifecycleListener($registry, new NullLogger());

        $listener->onResponseMapped(new ResponseMapped('tmdb', 'get_movie', 42.0, 200, 'App\Foo', microtime(true)));

        self::assertEmpty($this->samplesFor($registry, 'integration_mapping_duration_ms'));
    }

    #[Test]
    public function aReceivedResponseThatFailsToMapCountsAsAMappingFailureWithItsRealStatus(): void
    {
        $registry = new CollectorRegistry(new InMemory(), registerDefaultMetrics: false);
        $listener = new PrometheusLifecycleListener($registry, new NullLogger());

        $listener->onResponseReceived(new ResponseReceived('tmdb', 'get_movie', 40.0, 200, microtime(true)));
        $listener->onRequestFailed(new RequestFailed('tmdb', 'get_movie', 41.0, 0, 'TypeError', 'Integration request failed.', microtime(true)));

        $mappingFailures = $this->samplesFor($registry, 'integration_mapping_failures_total');
        self::assertCount(1, $mappingFailures);
        self::assertSame(['tmdb', 'get_movie', '2xx'], $mappingFailures[0]->getLabelValues());

        // The general failure counter keeps the status the event carries.
        self::assertSame(['tmdb', 'get_movie', 'network'], $this->samplesFor($registry, 'integration_failures_total')[0]->getLabelValues());
    }

    #[Test]
    public function aFailureWithoutAReceivedResponseIsNotAMappingFailure(): void
    {
        $registry = new CollectorRegistry(new InMemory(), registerDefaultMetrics: false);
        $listener = new PrometheusLifecycleListener($registry, new NullLogger());

        $listener->onRequestFailed(new RequestFailed('supplier', 'get_prices', 15.0, 503, 'RuntimeException', 'Upstream request failed.', microtime(true)));

        self::assertEmpty($this->samplesFor($registry, 'integration_mapping_failures_total'));
        self::assertCount(1, $this->samplesFor($registry, 'integration_failures_total'));
    }

    #[Test]
    public function aReceivedResponseIsOnlyPairedOnce(): void
    {
        $registry = new CollectorRegistry(new InMemory(), registerDefaultMetrics: false);
        $listener = new PrometheusLifecycleListener($registry, new NullLogger());

        $listener->onResponseReceived(new ResponseReceived('tmdb', 'get_movie', 40.0, 200, microtime(true)));
        $listener->onResponseMapped(new ResponseMapped('tmdb', 'get_movie', 42.0, 200, 'App\Foo', microtime(true)));
        $listener->onRequestFailed(new RequestFailed('tmdb', 'get_movie', 15.0, 0, 'RuntimeException', 'Integration request failed.', microtime(true)));

        self::assertEmpty($this->samplesFor($registry, 'integration_mapping_failures_total'));
    }

    #[Test]
    public function registeredLabelsAreExactlyIntegrationActionAndStatusClass(): void
    {
        $registry = new CollectorRegistry(new InMemory(), registerDefaultMetrics: false);
        $listener = new PrometheusLifecycleListener($registry, new NullLogger());

        $listener->onResponseReceived(new ResponseReceived('tmdb', 'get_movie', 9.0, 200, microtime(true), 'should-never-become-a-label'));
        $listener->onResponseMapped(new ResponseMapped(
            integrationName: 'tmdb',
            action: 'get_movie',
            durationMs: 10.0,
            statusCode: 200,
            responseClass: 'App\Foo',
            timestamp: microtime(true),
            requestKey: 'should-never-become-a-label',
        ));
        $listener->onResponseReceived(new ResponseReceived('tmdb', 'get_movie', 9.0, 200, microtime(true)));
        $listener->onRequestFailed(new RequestFailed('tmdb', 'get_movie', 10.0, 0, 'TypeError', 'Integration request failed.', microtime(true)));

        $families = $registry->getMetricFamilySamples();
        self::assertCount(5, $families);
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
