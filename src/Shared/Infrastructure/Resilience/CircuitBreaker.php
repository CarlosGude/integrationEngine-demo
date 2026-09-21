<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Resilience;

use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\NativeClock;

/**
 * Circuit Breaker pattern: Prevent cascading failures.
 *
 * States:
 * - CLOSED (normal): Requests pass through
 * - OPEN (failing): Requests fail fast without calling upstream
 * - HALF_OPEN (recovering): A few requests go through to test recovery
 *
 * Example: Supplier API is down
 * → 5 failures in 10 seconds → switch to OPEN
 * → Requests fail instantly (without waiting for timeout)
 * → After 30 seconds → try HALF_OPEN
 * → If 1 request succeeds → back to CLOSED
 * → If it still fails → back to OPEN
 */
final class CircuitBreaker
{
    private const STATE_CLOSED = 'closed';
    private const STATE_OPEN = 'open';
    private const STATE_HALF_OPEN = 'half_open';

    private const FAILURE_THRESHOLD = 5;          // failures to trigger OPEN
    private const FAILURE_WINDOW_SEC = 10;        // time window for threshold
    private const TIMEOUT_SEC = 30;               // time in OPEN before HALF_OPEN

    private string $state = self::STATE_CLOSED;
    private int $failureCount = 0;
    private int $successCount = 0;
    private ?int $openedAt = null;

    public function __construct(private readonly ClockInterface $clock = new NativeClock())
    {
    }

    /**
     * Check if request should be allowed.
     * tour:start resilience/circuit-breaker-check
     */
    public function allow(): bool
    {
        $now = $this->clock->now()->getTimestamp();

        if ($this->state === self::STATE_OPEN) {
            $this->maybeHalfOpen($now);
        }

        return $this->state !== self::STATE_OPEN;
    }
    // tour:end

    /**
     * Record a successful request.
     */
    public function recordSuccess(): void
    {
        if ($this->state === self::STATE_CLOSED) {
            $this->failureCount = 0;
        } elseif ($this->state === self::STATE_HALF_OPEN) {
            $this->transitionToClosed();
        }
    }

    /**
     * Record a failed request.
     * tour:start resilience/circuit-breaker-failure
     */
    public function recordFailure(): void
    {
        $now = $this->clock->now()->getTimestamp();

        if ($this->state === self::STATE_HALF_OPEN) {
            $this->transitionToOpen($now);

            return;
        }

        ++$this->failureCount;

        if ($this->failureCount >= self::FAILURE_THRESHOLD) {
            $this->transitionToOpen($now);
        }
    }
    // tour:end

    public function getState(): string
    {
        return $this->state;
    }

    private function maybeHalfOpen(int $now): void
    {
        if ($this->openedAt !== null && $now - $this->openedAt >= self::TIMEOUT_SEC) {
            $this->transitionToHalfOpen();
        }
    }

    private function transitionToClosed(): void
    {
        $this->state = self::STATE_CLOSED;
        $this->failureCount = 0;
        $this->successCount = 0;
        $this->openedAt = null;
    }

    private function transitionToOpen(int $now): void
    {
        $this->state = self::STATE_OPEN;
        $this->openedAt = $now;
    }

    private function transitionToHalfOpen(): void
    {
        $this->state = self::STATE_HALF_OPEN;
        $this->successCount = 0;
    }
}
