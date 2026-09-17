<?php

declare(strict_types=1);

namespace Tests\Shared\Stats;

use App\Shared\Stats\Median;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MedianTest extends TestCase
{
    #[Test]
    public function calculateMedianOfOddCount(): void
    {
        $values = [3, 1, 2];
        $median = Median::calculate($values);

        self::assertSame(2.0, $median);
    }

    #[Test]
    public function calculateMedianOfEvenCount(): void
    {
        $values = [4, 1, 3, 2];
        $median = Median::calculate($values);

        self::assertSame(2.5, $median);
    }

    #[Test]
    public function calculateMedianOfFloats(): void
    {
        $values = [1.5, 2.5, 3.5];
        $median = Median::calculate($values);

        self::assertSame(2.5, $median);
    }

    #[Test]
    public function throwsOnEmptyArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Median::calculate([]);
    }

    #[Test]
    public function calculateMedianOfSingleValue(): void
    {
        $values = [42];
        $median = Median::calculate($values);

        self::assertSame(42.0, $median);
    }
}
