<?php

declare(strict_types=1);

namespace App\Integrations\Supplier\Mappers;

use App\Integrations\Supplier\GetPrices\GetPricesAction;
use App\Integrations\Supplier\GetPrices\GetPricesResponse;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;
use IntegrationEngine\Utils\CsvParser;

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

        if (trim($csvContent) === '') {
            return new GetPricesResponse([]);
        }

        try {
            $rows = CsvParser::parse($csvContent);
        } catch (\InvalidArgumentException) {
            return new GetPricesResponse([]);
        }

        return new GetPricesResponse($rows);
    }
}
