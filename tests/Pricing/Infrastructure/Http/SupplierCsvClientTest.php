<?php

declare(strict_types=1);

namespace Tests\Pricing\Infrastructure\Http;

use App\Integrations\Supplier\GetPrices\GetPricesAction;
use App\Pricing\Infrastructure\Http\SupplierCsvClient;
use IntegrationEngine\Core\Exception\RequestResponseException;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class SupplierCsvClientTest extends TestCase
{
    public function testReturnsCsvPayloadWithHeadersAndStatus(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('getStatusCode')->willReturn(200);
        $response->expects(self::once())->method('getContent')->with(false)->willReturn("sku,price\nA,3.99\n");
        $response->expects(self::once())->method('getHeaders')->with(false)->willReturn(['content-type' => ['text/csv']]);

        $http = $this->createMock(HttpClientInterface::class);
        $http->expects(self::once())
            ->method('request')
            ->with('GET', 'http://supplier/prices.csv', ['headers' => []])
            ->willReturn($response);

        $client = new SupplierCsvClient($http, 'http://supplier');
        $action = GetPricesAction::create('GET', '/prices.csv');

        self::assertSame([
            'body' => ['csv' => "sku,price\nA,3.99\n"],
            'headers' => ['content-type' => ['text/csv']],
            'statusCode' => 200,
        ], $client->send($action));
    }

    public function testWrapsHttpErrorAsRequestResponseException(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(503);
        $response->method('getContent')->with(false)->willReturn('unavailable');

        $http = $this->createMock(HttpClientInterface::class);
        $http->method('request')->willReturn($response);

        $client = new SupplierCsvClient($http, 'http://supplier');
        $action = GetPricesAction::create('GET', '/prices.csv');

        $this->expectException(RequestResponseException::class);
        $this->expectExceptionMessage('GET /prices.csv returned HTTP 503');

        $client->send($action);
    }

    public function testWrapsTransportFailure(): void
    {
        $http = $this->createMock(HttpClientInterface::class);
        $http->method('request')->willThrowException(new \RuntimeException('connection refused'));

        $client = new SupplierCsvClient($http, 'http://supplier');
        $action = GetPricesAction::create('GET', '/prices.csv');

        $this->expectException(RequestResponseException::class);
        $this->expectExceptionMessage('Network error on GET /prices.csv: connection refused');

        $client->send($action);
    }
}
