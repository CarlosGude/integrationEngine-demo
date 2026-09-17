<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Integrations\Supplier;

use App\Pricing\Infrastructure\Http\CsvClientAdapter;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class GetPricesMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return GetPricesAction::class;
    }

    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        /** @var string $csvContent */
        $csvContent = $response['body'] ?? '';

        $rows = self::parseCSV($csvContent);

        return new GetPricesResponse($rows);
    }

    /**
     * @return array<int, array{sku: string, price: string, currency: string, stock?: string}>
     */
    private static function parseCSV(string $csvContent): array
    {
        $lines = \explode("\n", \trim($csvContent));
        if (empty($lines) || (count($lines) === 1 && empty($lines[0]))) {
            return [];
        }

        $header = \str_getcsv(\array_shift($lines));
        if (empty($header)) {
            throw new \InvalidArgumentException('CSV header is empty or missing');
        }

        $rows = [];

        foreach ($lines as $line) {
            if (empty(\trim($line))) {
                continue;
            }

            $values = \str_getcsv($line);
            if (\count($values) !== \count($header)) {
                throw new \InvalidArgumentException('CSV row has mismatched column count');
            }

            $rows[] = \array_combine($header, $values);
        }

        return $rows;
    }
}
