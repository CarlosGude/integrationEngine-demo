<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Resilience;

use App\Shared\Infrastructure\Resilience\FallbackStrategy;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FallbackStrategyTest extends TestCase
{
    #[Test]
    public function nullFallbackReturnsNull(): void
    {
        self::assertNull(FallbackStrategy::nullFallback());
    }

    #[Test]
    public function cacheFallbackReturnsTheCachedValueUnchanged(): void
    {
        self::assertSame('cached-value', FallbackStrategy::cacheFallback('cached-value'));
    }

    #[Test]
    public function cacheFallbackIgnoresTheCachedAtArgument(): void
    {
        $result = FallbackStrategy::cacheFallback(['prices' => [1, 2, 3]], new \DateTimeImmutable('-2 hours'));

        self::assertSame(['prices' => [1, 2, 3]], $result);
    }

    #[Test]
    public function defaultFallbackReturnsTheGivenDefault(): void
    {
        self::assertSame(0, FallbackStrategy::defaultFallback(0));
        self::assertSame('n/a', FallbackStrategy::defaultFallback('n/a'));
    }
}
