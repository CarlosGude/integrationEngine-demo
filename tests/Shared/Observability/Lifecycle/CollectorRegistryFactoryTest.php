<?php

declare(strict_types=1);

namespace Tests\Shared\Observability\Lifecycle;

use App\Shared\Observability\Lifecycle\CollectorRegistryFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Psr\Log\LoggerInterface;

final class CollectorRegistryFactoryTest extends TestCase
{
    #[Test]
    public function inMemoryStorageNeverRegistersDefaultMetricsAndNeverWarns(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');

        $factory = new CollectorRegistryFactory('in_memory', '127.0.0.1', 6379, $logger);

        $registry = $factory->create();

        self::assertInstanceOf(CollectorRegistry::class, $registry);
        self::assertSame([], $registry->getMetricFamilySamples());
    }

    #[Test]
    public function apcuStorageFallsBackToInMemoryAndWarnsWhenTheExtensionIsMissing(): void
    {
        if (\extension_loaded('apcu')) {
            self::markTestSkipped('This environment has apcu loaded; the fallback path cannot be exercised.');
        }

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('METRICS_STORAGE=apcu but the apcu extension is not loaded'
                .'; falling back to in-memory metrics storage (not shared across workers, requests will not accumulate).');

        $factory = new CollectorRegistryFactory('apcu', '127.0.0.1', 6379, $logger);

        self::assertSame([], $factory->create()->getMetricFamilySamples());
    }

    #[Test]
    public function redisStorageFallsBackToInMemoryAndWarnsWhenTheExtensionIsMissing(): void
    {
        if (\extension_loaded('redis')) {
            self::markTestSkipped('This environment has redis loaded; the fallback path cannot be exercised.');
        }

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('METRICS_STORAGE=redis but the redis extension is not loaded'
                .'; falling back to in-memory metrics storage (not shared across workers, requests will not accumulate).');

        $factory = new CollectorRegistryFactory('redis', '127.0.0.1', 6379, $logger);

        self::assertSame([], $factory->create()->getMetricFamilySamples());
    }

    #[Test]
    public function unknownStorageFallsBackToInMemoryAndWarns(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('Unknown METRICS_STORAGE "bogus"'
                .'; falling back to in-memory metrics storage (not shared across workers, requests will not accumulate).');

        $factory = new CollectorRegistryFactory('bogus', '127.0.0.1', 6379, $logger);

        self::assertSame([], $factory->create()->getMetricFamilySamples());
    }
}
