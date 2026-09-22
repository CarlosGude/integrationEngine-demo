<?php

declare(strict_types=1);

namespace Tests\Pricing\Infrastructure\Integrations\Supplier;

use App\Integrations\Supplier\GetPrices\GetPricesAction;
use App\Integrations\Supplier\GetPrices\GetPricesResponse;
use App\Integrations\Supplier\Mappers\GetPricesMapper;
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
        $response = GetPricesMapper::map($action, ['body' => $csvContent], []);

        self::assertInstanceOf(GetPricesResponse::class, $response);
        $prices = $response->prices();
        self::assertCount(2, $prices);
        self::assertSame('MOVIE-001', $prices[0]['sku']);
        self::assertSame('3.99', $prices[0]['price']);
    }

    #[Test]
    public function whitespaceOnlyBodyMapsToAnEmptyResponse(): void
    {
        $action = GetPricesAction::create('GET', '/prices.csv');
        $response = GetPricesMapper::map($action, ['body' => "   \n  "], []);

        self::assertInstanceOf(GetPricesResponse::class, $response);
        self::assertSame([], $response->prices());
    }

    #[Test]
    public function missingBodyMapsToAnEmptyResponse(): void
    {
        $action = GetPricesAction::create('GET', '/prices.csv');
        $response = GetPricesMapper::map($action, [], []);
        \assert($response instanceof GetPricesResponse);

        self::assertSame([], $response->prices());
    }
}
