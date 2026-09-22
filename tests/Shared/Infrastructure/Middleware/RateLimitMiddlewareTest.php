<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Middleware;

use App\Integrations\Supplier\GetPrices\GetPricesAction;
use App\Shared\Infrastructure\Middleware\RateLimitMiddleware;
use IntegrationEngine\Core\Contract\Client\AbstractClientMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RateLimitMiddlewareTest extends TestCase
{
    private RateLimitMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new RateLimitMiddleware();

        // The rate limit counter is process-wide static state; reset it so
        // each test starts from a clean window regardless of test order.
        $ref = new \ReflectionClass(RateLimitMiddleware::class);
        $ref->getProperty('requestsThisSecond')->setValue(null, 0);
        $ref->getProperty('lastSecond')->setValue(null, 0);
    }

    #[Test]
    public function rateLimitMiddlewareExtendsAbstractClientMiddleware(): void
    {
        /** @phpstan-ignore function.alreadyNarrowedType, staticMethod.alreadyNarrowedType */
        self::assertTrue(\is_subclass_of(RateLimitMiddleware::class, AbstractClientMiddleware::class));
    }

    #[Test]
    public function processCallsNextAndReturnsItsResult(): void
    {
        $result = $this->middleware->process($this->action(), null, null, static fn (): array => ['ok' => true]);

        self::assertSame(['ok' => true], $result);
    }

    #[Test]
    public function allowsUpToFortyRequestsPerSecond(): void
    {
        $calls = 0;
        $next = static function () use (&$calls): array {
            ++$calls;

            return [];
        };

        for ($i = 0; $i < 40; ++$i) {
            $this->middleware->process($this->action(), null, null, $next);
        }

        self::assertSame(40, $calls);
    }

    #[Test]
    public function theFortyFirstRequestInTheSameSecondIsRejected(): void
    {
        $next = static fn (): array => [];

        for ($i = 0; $i < 40; ++$i) {
            $this->middleware->process($this->action(), null, null, $next);
        }

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Rate limit exceeded (40 requests/second max)');
        $this->middleware->process($this->action(), null, null, $next);
    }

    #[Test]
    public function processManyChecksEveryRequestBeforeDispatching(): void
    {
        $nextCalled = false;
        $next = static function () use (&$nextCalled): array {
            $nextCalled = true;

            return [];
        };

        // processMany() only counts requests (foreach ($requests as $_ => $_)),
        // it never reads them, so plain placeholders exercise it fully.
        /** @phpstan-ignore argument.type */
        $result = $this->middleware->processMany(array_fill(0, 5, null), $next);

        self::assertSame([], $result);
        self::assertTrue($nextCalled);
    }

    #[Test]
    public function processManyRejectsWhenTheBatchAloneExceedsTheLimit(): void
    {
        $nextCalled = false;
        $next = static function () use (&$nextCalled): array {
            $nextCalled = true;

            return [];
        };

        $this->expectException(\RuntimeException::class);
        try {
            /** @phpstan-ignore argument.type */
            $this->middleware->processMany(array_fill(0, 41, null), $next);
        } finally {
            // The 41st checkRateLimit() call throws before $next() is ever
            // reached: the whole batch must be checked up front.
            self::assertFalse($nextCalled);
        }
    }

    #[Test]
    public function theCounterResetsOnceTheWallClockSecondAdvances(): void
    {
        $ref = new \ReflectionClass(RateLimitMiddleware::class);
        $requests = $ref->getProperty('requestsThisSecond');
        $lastSecond = $ref->getProperty('lastSecond');

        // Simulate "40 requests already made, one second ago".
        $requests->setValue(null, 40);
        $lastSecond->setValue(null, time() - 1);

        // A new second means a fresh window: this must not throw.
        $result = $this->middleware->process($this->action(), null, null, static fn (): array => ['ok' => true]);

        self::assertSame(['ok' => true], $result);
        self::assertSame(1, $requests->getValue(null));
    }

    private function action(): GetPricesAction
    {
        return GetPricesAction::create('GET', '/prices');
    }
}
