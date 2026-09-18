<?php

declare(strict_types=1);

namespace App\Integrations\Supplier\Mappers;

use App\Integrations\Supplier\GetPrices\GetPricesAction;
use App\Integrations\Supplier\GetPrices\GetPricesResponse;
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
     * @return list<array<string, string>>
     */
    private static function parseCSV(string $csvContent): array
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
