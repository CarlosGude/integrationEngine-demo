<?php

declare(strict_types=1);

namespace Tests\Pricing\Infrastructure\Http;

use App\Pricing\Infrastructure\Http\CsvClientAdapter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CsvClientAdapterTest extends TestCase
{
    #[Test]
    public function parsesValidCSV(): void
    {
        $adapter = new CsvClientAdapter($this->createMock(\Symfony\Contracts\HttpClient\HttpClientInterface::class));

        $csv = <<<'CSV'
sku,price,currency
MOVIE-001,3.99,USD
MOVIE-002,4.99,USD
MOVIE-003,2.99,USD
CSV;

        $result = $adapter->parseCSV($csv);

        self::assertCount(3, $result);
        self::assertSame('MOVIE-001', $result[0]['sku']);
        self::assertSame('3.99', $result[0]['price']);
        self::assertSame('USD', $result[0]['currency']);
    }

    #[Test]
    public function skipsEmptyLines(): void
    {
        $adapter = new CsvClientAdapter($this->createMock(\Symfony\Contracts\HttpClient\HttpClientInterface::class));

        $csv = <<<'CSV'
sku,price,currency

MOVIE-001,3.99,USD

MOVIE-002,4.99,USD
CSV;

        $result = $adapter->parseCSV($csv);

        self::assertCount(2, $result);
    }

    #[Test]
    public function throwsOnMisalignedColumns(): void
    {
        $adapter = new CsvClientAdapter($this->createMock(\Symfony\Contracts\HttpClient\HttpClientInterface::class));

        $csv = <<<'CSV'
sku,price,currency
MOVIE-001,3.99,USD,EXTRA
CSV;

        $this->expectException(\InvalidArgumentException::class);
        $adapter->parseCSV($csv);
    }

    #[Test]
    public function handlesEmptyCSV(): void
    {
        $adapter = new CsvClientAdapter($this->createMock(\Symfony\Contracts\HttpClient\HttpClientInterface::class));

        $result = $adapter->parseCSV('');

        self::assertEmpty($result);
    }
}
