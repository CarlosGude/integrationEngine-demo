<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Http;

final class CsvClientAdapter implements CsvClientAdapterInterface
{
    public function parseCSV(string $csvContent): array
    {
        $csvContent = \trim($csvContent);
        if ($csvContent === '') {
            return [];
        }

        $lines = \explode("\n", $csvContent);
        $header = self::columns(\array_shift($lines));

        $rows = [];

        foreach ($lines as $line) {
            if (empty(\trim($line))) {
                continue;
            }

            $values = self::columns($line);
            if (\count($values) !== \count($header)) {
                throw new \InvalidArgumentException('CSV row has mismatched column count');
            }

            $rows[] = \array_combine($header, $values);
        }

        return $rows;
    }

    /** @return list<string> */
    private static function columns(string $line): array
    {
        return \array_map(static fn (?string $value): string => (string) $value, \str_getcsv($line));
    }
}
