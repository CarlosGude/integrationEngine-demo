<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure;

use App\Shared\Infrastructure\ChaosMonkey;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

final class ChaosMonkeyTest extends TestCase
{
    #[Test]
    public function zeroRateNeverFails(): void
    {
        $chaos = new ChaosMonkey(timeoutFailureRate: 0, rateLimitFailureRate: 0, serviceUnavailableRate: 0);

        for ($i = 0; $i < 20; ++$i) {
            self::assertFalse($chaos->shouldTimeout());
            self::assertFalse($chaos->shouldRateLimit());
            self::assertFalse($chaos->shouldServiceFail());
        }
    }

    #[Test]
    public function hundredPercentRateAlwaysFails(): void
    {
        $chaos = new ChaosMonkey(timeoutFailureRate: 100, rateLimitFailureRate: 100, serviceUnavailableRate: 100);

        for ($i = 0; $i < 20; ++$i) {
            self::assertTrue($chaos->shouldTimeout());
            self::assertTrue($chaos->shouldRateLimit());
            self::assertTrue($chaos->shouldServiceFail());
        }
    }

    #[Test]
    public function negativeRateIsTreatedAsZero(): void
    {
        $chaos = new ChaosMonkey(timeoutFailureRate: -50);

        self::assertFalse($chaos->shouldTimeout());
    }

    #[Test]
    public function aboveHundredRateIsTreatedAsAlwaysFail(): void
    {
        $chaos = new ChaosMonkey(timeoutFailureRate: 150);

        self::assertTrue($chaos->shouldTimeout());
    }

    #[Test]
    public function injectTimeoutThrowsARetryableTransportException(): void
    {
        $chaos = new ChaosMonkey();

        try {
            $chaos->injectTimeout();
        } catch (TransportExceptionInterface $e) {
            self::assertSame('Request timeout (simulated)', $e->getMessage());
            self::assertSame(0, $e->getCode());

            return;
        }
    }

    #[Test]
    public function injectRateLimitThrowsA429HttpException(): void
    {
        $chaos = new ChaosMonkey();

        try {
            $chaos->injectRateLimit();
        } catch (HttpExceptionInterface $e) {
            self::assertSame('Too Many Requests (simulated)', $e->getMessage());
            self::assertSame(429, $e->getResponse()->getStatusCode());

            return;
        }
    }

    #[Test]
    public function injectServiceUnavailableThrowsA503HttpException(): void
    {
        $chaos = new ChaosMonkey();

        try {
            $chaos->injectServiceUnavailable();
        } catch (HttpExceptionInterface $e) {
            self::assertSame('Service Unavailable (simulated)', $e->getMessage());
            self::assertSame(503, $e->getResponse()->getStatusCode());

            return;
        }
    }

    #[Test]
    public function fluentSettersReturnTheSameInstanceAndUpdateTheRate(): void
    {
        $chaos = new ChaosMonkey();

        $result = $chaos->withTimeoutRate(100)->withRateLimitRate(100)->withServiceUnavailableRate(100);

        self::assertSame($chaos, $result);
        self::assertTrue($chaos->shouldTimeout());
        self::assertTrue($chaos->shouldRateLimit());
        self::assertTrue($chaos->shouldServiceFail());
    }

    #[Test]
    public function ratesAreIndependentOfEachOther(): void
    {
        $chaos = (new ChaosMonkey())->withTimeoutRate(100);

        self::assertTrue($chaos->shouldTimeout());
        self::assertFalse($chaos->shouldRateLimit());
        self::assertFalse($chaos->shouldServiceFail());
    }
}
