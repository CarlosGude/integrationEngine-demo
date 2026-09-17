<?php

declare(strict_types=1);

namespace Tests\Pricing\Infrastructure\Integrations\Supplier;

use App\Pricing\Infrastructure\Integrations\Supplier\GetPricesAction;
use App\Pricing\Infrastructure\Integrations\Supplier\GetPricesMapper;
use App\Pricing\Infrastructure\Integrations\Supplier\GetPricesResponse;
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
}
