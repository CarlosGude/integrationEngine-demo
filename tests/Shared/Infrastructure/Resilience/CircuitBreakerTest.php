<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Resilience;

use App\Shared\Infrastructure\Resilience\CircuitBreaker;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class CircuitBreakerTest extends TestCase
{
    #[Test]
    public function startsClosedAndAllowsRequests(): void
    {
        $breaker = new CircuitBreaker(new MockClock());

        self::assertSame('closed', $breaker->getState());
        self::assertTrue($breaker->allow());
    }

    #[Test]
    public function staysClosedBelowTheFailureThreshold(): void
    {
        $breaker = new CircuitBreaker(new MockClock());

        for ($i = 0; $i < 4; ++$i) {
            $breaker->recordFailure();
        }

        self::assertSame('closed', $breaker->getState());
        self::assertTrue($breaker->allow());
    }

    #[Test]
    public function opensExactlyAtTheFailureThreshold(): void
    {
        $breaker = new CircuitBreaker(new MockClock());

        for ($i = 0; $i < 5; ++$i) {
            $breaker->recordFailure();
        }

        self::assertSame('open', $breaker->getState());
        self::assertFalse($breaker->allow());
    }

    #[Test]
    public function recordSuccessWhileClosedResetsTheFailureCount(): void
    {
        $breaker = new CircuitBreaker(new MockClock());

        $breaker->recordFailure();
        $breaker->recordFailure();
        $breaker->recordFailure();
        $breaker->recordFailure();
        $breaker->recordSuccess();

        // If the counter had not reset, one more failure would open it
        // (4 + 1 = 5 == threshold). It should still take 5 more instead.
        $breaker->recordFailure();
        self::assertSame('closed', $breaker->getState());

        for ($i = 0; $i < 3; ++$i) {
            $breaker->recordFailure();
        }
        self::assertSame('closed', $breaker->getState());

        $breaker->recordFailure();
        self::assertSame('open', $breaker->getState());
    }

    #[Test]
    public function staysOpenBeforeTheTimeoutElapses(): void
    {
        $clock = new MockClock();
        $breaker = new CircuitBreaker($clock);

        for ($i = 0; $i < 5; ++$i) {
            $breaker->recordFailure();
        }
        self::assertSame('open', $breaker->getState());

        $clock->modify('+29 seconds');

        self::assertFalse($breaker->allow());
        self::assertSame('open', $breaker->getState());
    }

    #[Test]
    public function transitionsToHalfOpenOnceTheTimeoutElapses(): void
    {
        $clock = new MockClock();
        $breaker = new CircuitBreaker($clock);

        for ($i = 0; $i < 5; ++$i) {
            $breaker->recordFailure();
        }

        $clock->modify('+30 seconds');

        self::assertTrue($breaker->allow());
        self::assertSame('half_open', $breaker->getState());
    }

    #[Test]
    public function halfOpenSuccessClosesTheCircuitAndResetsFailures(): void
    {
        $clock = new MockClock();
        $breaker = new CircuitBreaker($clock);

        for ($i = 0; $i < 5; ++$i) {
            $breaker->recordFailure();
        }
        $clock->modify('+30 seconds');
        $breaker->allow();
        self::assertSame('half_open', $breaker->getState());

        $breaker->recordSuccess();

        self::assertSame('closed', $breaker->getState());
        self::assertTrue($breaker->allow());
    }

    #[Test]
    public function halfOpenFailureReopensTheCircuitImmediately(): void
    {
        $clock = new MockClock();
        $breaker = new CircuitBreaker($clock);

        for ($i = 0; $i < 5; ++$i) {
            $breaker->recordFailure();
        }
        $clock->modify('+30 seconds');
        $breaker->allow();
        self::assertSame('half_open', $breaker->getState());

        $breaker->recordFailure();

        self::assertSame('open', $breaker->getState());
        self::assertFalse($breaker->allow());

        // The re-open should have reset the "opened at" clock: it takes a
        // fresh 30s from here, not the original window.
        $clock->modify('+29 seconds');
        self::assertFalse($breaker->allow());
        $clock->modify('+1 second');
        self::assertTrue($breaker->allow());
    }

    #[Test]
    public function defaultConstructorUsesARealClock(): void
    {
        $breaker = new CircuitBreaker();

        self::assertSame('closed', $breaker->getState());
    }
}
