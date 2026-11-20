<?php

declare(strict_types=1);

namespace Tests\Pricing\Infrastructure\Integrations\Supplier;

use App\Integrations\Supplier\GetPrices\GetPricesAction;
use App\Integrations\Supplier\GetPrices\GetPricesResponse;
use App\Integrations\Supplier\Mappers\GetPricesMapper;
use App\Shared\Infrastructure\Integration\UnexpectedApiResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetPricesMapperTest extends TestCase
{
    #[Test]
    public function mapsPricesResponse(): void
    {
        $csvContent = <<<'CSV'
sku,price,currency
MOVIE-001,3.99,USD
MOVIE-002,4.99,USD
CSV;

        $action = GetPricesAction::create('GET', '/prices.csv');
        $response = GetPricesMapper::map($action, ['csv' => $csvContent], []);

        self::assertInstanceOf(GetPricesResponse::class, $response);
        $prices = $response->prices();
        self::assertCount(2, $prices);
        self::assertSame('MOVIE-001', $prices[0]['sku']);
        self::assertSame('3.99', $prices[0]['price']);
    }

    #[Test]
    public function aHeaderOnlyCsvMapsToAnEmptyPriceList(): void
    {
        $action = GetPricesAction::create('GET', '/prices.csv');
        $response = GetPricesMapper::map($action, ['csv' => "sku,price,currency\n"], []);

        self::assertInstanceOf(GetPricesResponse::class, $response);
        self::assertSame([], $response->prices());
    }

    #[Test]
    public function rejectsAWhitespaceOnlyBody(): void
    {
        $action = GetPricesAction::create('GET', '/prices.csv');

        $this->expectException(UnexpectedApiResponse::class);
        $this->expectExceptionMessage('Supplier prices CSV: field "csv" is missing or not a CSV document with a header row.');

        GetPricesMapper::map($action, ['csv' => "   \n  "], []);
    }

    #[Test]
    public function rejectsAMissingBody(): void
    {
        $action = GetPricesAction::create('GET', '/prices.csv');

        $this->expectException(UnexpectedApiResponse::class);
        $this->expectExceptionMessage('Supplier prices CSV: field "csv" is missing or not a string.');

        GetPricesMapper::map($action, [], []);
    }
}
