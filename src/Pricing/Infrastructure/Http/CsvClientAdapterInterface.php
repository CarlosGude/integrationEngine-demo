<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Http;

interface CsvClientAdapterInterface
{
    /**
     * @return array<int, array<string, string>>
     */
    public function parseCSV(string $csvContent): array;
}
