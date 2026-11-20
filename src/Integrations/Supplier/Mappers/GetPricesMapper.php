<?php

declare(strict_types=1);

namespace App\Integrations\Supplier\Mappers;

use App\Integrations\Supplier\GetPrices\GetPricesAction;
use App\Integrations\Supplier\GetPrices\GetPricesResponse;
use App\Shared\Infrastructure\Integration\ResponseFields;
use App\Shared\Infrastructure\Integration\UnexpectedApiResponse;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;
use IntegrationEngine\Utils\CsvParser;

final class GetPricesMapper extends AbstractMapper
{
    private const SOURCE = 'Supplier prices CSV';

    public static function getAction(): string
    {
        return GetPricesAction::class;
    }

    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        $csvContent = ResponseFields::string($response, 'csv', self::SOURCE);

        // A body without even a header row is not a price list, so it is rejected rather than read as empty.
        if (trim($csvContent) === '') {
            throw UnexpectedApiResponse::invalidField(self::SOURCE, 'csv', 'a CSV document with a header row');
        }

        try {
            $rows = CsvParser::parse($csvContent);
        } catch (\InvalidArgumentException $e) {
            throw UnexpectedApiResponse::unreadable(self::SOURCE, $e);
        }

        return new GetPricesResponse(array_values($rows));
    }
}
