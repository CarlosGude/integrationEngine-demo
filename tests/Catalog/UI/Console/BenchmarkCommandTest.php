<?php

declare(strict_types=1);

namespace Tests\Catalog\UI\Console;

use App\Shared\Stats\Median;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BenchmarkCommandTest extends TestCase
{
    #[Test]
    public function benchmarkResultsAreCalculatedCorrectly(): void
    {
        $times = [100, 150, 120, 110, 130];
        $median = Median::calculate($times);

        self::assertGreaterThanOrEqual(100, $median);
        self::assertLessThanOrEqual(150, $median);
    }

    #[Test]
    public function benchmarkCanHandleVariableExecutionTimes(): void
    {
        $times = [100.5, 200.3, 150.7, 175.2, 125.9];
        $median = Median::calculate($times);

        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertIsFloat($median);
        self::assertGreaterThan(0, $median);
    }

    #[Test]
    public function benchmarkMetricsAreReasonable(): void
    {
        $parallelTimes = [250, 260, 245, 255, 250];
        $median = Median::calculate($parallelTimes);
        $min = \min($parallelTimes);
        $max = \max($parallelTimes);

        self::assertSame(250.0, $median);
        self::assertSame(245, $min);
        self::assertSame(260, $max);
    }
}
