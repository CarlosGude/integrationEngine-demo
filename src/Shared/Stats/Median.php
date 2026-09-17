<?php

declare(strict_types=1);

namespace App\Shared\Stats;

final class Median
{
    /**
     * @param array<float|int> $values
     */
    public static function calculate(array $values): float
    {
        if (empty($values)) {
            throw new \InvalidArgumentException('Cannot calculate median of empty array');
        }

        $sorted = $values;
        \sort($sorted);
        $count = \count($sorted);
        $middle = (int) \floor(($count - 1) / 2);

        if ($count % 2 === 0) {
            return ($sorted[$middle] + $sorted[$middle + 1]) / 2;
        }

        return (float) $sorted[$middle];
    }
}
