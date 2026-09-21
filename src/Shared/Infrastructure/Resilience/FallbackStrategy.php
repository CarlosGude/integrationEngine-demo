<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Resilience;

/**
 * Fallback strategies when an API call fails permanently.
 *
 * When all retries are exhausted, you have options:
 * - NULL fallback: Return null/empty (graceful degradation)
 * - CACHE fallback: Return last known good value (for read operations)
 * - DEFAULT fallback: Return a safe default (for critical operations)
 *
 * Example: Supplier API is permanently down
 * → Retry 3 times with exponential backoff → all fail
 * → Use fallback: return cached prices from 1 hour ago
 * → User sees slightly stale data instead of error
 */
final class FallbackStrategy
{
    /**
     * Return null for missing data.
     * tour:start resilience/fallback-null
     */
    public static function nullFallback(): mixed
    {
        return null;
    }
    // tour:end

    /**
     * Return cached value (last known good).
     * tour:start resilience/fallback-cache
     */
    public static function cacheFallback(mixed $cachedValue, ?\DateTimeImmutable $cachedAt = null): mixed
    {
        // In production: check age of cached value
        // $age = (new \DateTimeImmutable('now'))->getTimestamp() - $cachedAt->getTimestamp();
        // if ($age > 3600) throw new \RuntimeException('Cache too old'); // Don't use stale data
        //
        return $cachedValue;
    }
    // tour:end

    /**
     * Return safe default for critical operations.
     * tour:start resilience/fallback-default
     */
    public static function defaultFallback(mixed $defaultValue): mixed
    {
        // Example: Supplier API fails
        // Return minimum stock quantity (0) instead of error
        // Prevents overbooking but may disappoint users
        return $defaultValue;
    }
    // tour:end
}
