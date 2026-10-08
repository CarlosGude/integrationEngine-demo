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
    public function apcuStorageFallsBackToInMemoryAndWarnsWhenApcuIsUnavailable(): void
    {
        // Extension loaded but disabled for the SAPI (apc.enable_cli
        // defaults to Off, which is exactly the state of a CLI-run test
        // suite even when apcu is compiled in — this is the scenario that
        // escaped extension_loaded()-only coverage before) is just as much
        // "unavailable" as the extension not being loaded at all.
        if (\extension_loaded('apcu') && \apcu_enabled()) {
            self::markTestSkipped('APCu is actually available and enabled here; the fallback path cannot be exercised.');
        }

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(self::logicalAnd(
                self::stringStartsWith('METRICS_STORAGE=apcu but '),
                self::stringContains('; falling back to in-memory metrics storage'),
            ));

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
