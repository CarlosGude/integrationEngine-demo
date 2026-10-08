<?php

declare(strict_types=1);

namespace App\Shared\Observability\Lifecycle;

use Prometheus\CollectorRegistry;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\APCng;
use Prometheus\Storage\InMemory;
use Prometheus\Storage\Redis;
use Psr\Log\LoggerInterface;

/**
 * PHP-FPM workers don't share memory between requests, so the metrics
 * registry needs storage that outlives a single request. Which backend to
 * use is an environment decision (METRICS_STORAGE), not a code one — this
 * factory only guarantees it never hands back a registry backed by a storage
 * that isn't actually available, falling back to an in-memory adapter (and
 * logging why) instead of letting the whole request fail.
 */
final readonly class CollectorRegistryFactory
{
    public function __construct(
        private string $storage,
        private string $redisHost,
        private int $redisPort,
        private LoggerInterface $logger,
    ) {
    }

    public function create(): CollectorRegistry
    {
        return new CollectorRegistry($this->resolveAdapter(), registerDefaultMetrics: false);
    }

    private function resolveAdapter(): Adapter
    {
        return match ($this->storage) {
            'in_memory' => new InMemory(),
            'redis' => $this->redisAdapter(),
            'apcu' => $this->apcuAdapter(),
            default => $this->fallback(\sprintf('Unknown METRICS_STORAGE "%s"', $this->storage)),
        };
    }

    private function apcuAdapter(): Adapter
    {
        if (!\extension_loaded('apcu')) {
            return $this->fallback('METRICS_STORAGE=apcu but the apcu extension is not loaded');
        }

        return new APCng();
    }

    private function redisAdapter(): Adapter
    {
        if (!\extension_loaded('redis')) {
            return $this->fallback('METRICS_STORAGE=redis but the redis extension is not loaded');
        }

        return new Redis(['host' => $this->redisHost, 'port' => $this->redisPort]);
    }

    private function fallback(string $reason): Adapter
    {
        $this->logger->warning(\sprintf(
            '%s; falling back to in-memory metrics storage (not shared across workers, requests will not accumulate).',
            $reason,
        ));

        return new InMemory();
    }
}
