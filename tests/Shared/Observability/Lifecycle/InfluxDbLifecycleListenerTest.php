<?php

declare(strict_types=1);

namespace Tests\Shared\Observability\Lifecycle;

use App\Shared\Observability\Lifecycle\InfluxDbLifecycleListener;
use InfluxDB2\Client;
use InfluxDB2\Model\WritePrecision;
use InfluxDB2\Point;
use InfluxDB2\WriteApi;
use IntegrationEngine\Core\Event\RequestFailed;
use IntegrationEngine\Core\Event\ResponseMapped;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

final class InfluxDbLifecycleListenerTest extends TestCase
{
    #[Test]
    public function disabledListenerNeverTouchesTheClient(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('createWriteApi');

        $listener = new InfluxDbLifecycleListener(false, $client, new NullLogger());

        $listener->onResponseMapped($this->responseMapped());
        $listener->onRequestFailed($this->requestFailed());
        $listener->flush();
    }

    #[Test]
    public function pointsAccumulateInMemoryAndAreNotWrittenBeforeFlush(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('createWriteApi');

        $listener = new InfluxDbLifecycleListener(true, $client, new NullLogger());

        $listener->onResponseMapped($this->responseMapped());
        $listener->onRequestFailed($this->requestFailed());
    }

    #[Test]
    public function flushWritesAllAccumulatedPointsInOneBatchWithMillisecondPrecision(): void
    {
        $writeApi = $this->createMock(WriteApi::class);
        $writeApi->expects(self::once())
            ->method('write')
            ->with(
                self::callback(static function (array $points): bool {
                    self::assertCount(2, $points);
                    foreach ($points as $point) {
                        self::assertInstanceOf(Point::class, $point);
                    }

                    return true;
                }),
                WritePrecision::MS,
            );

        $client = $this->createMock(Client::class);
        $client->expects(self::once())->method('createWriteApi')->willReturn($writeApi);

        $listener = new InfluxDbLifecycleListener(true, $client, new NullLogger());

        $listener->onResponseMapped($this->responseMapped());
        $listener->onRequestFailed($this->requestFailed());
        $listener->flush();
    }

    #[Test]
    public function flushWithNoAccumulatedPointsNeverTouchesTheClient(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('createWriteApi');

        $listener = new InfluxDbLifecycleListener(true, $client, new NullLogger());

        $listener->flush();
    }

    #[Test]
    public function flushClearsTheBufferSoASecondFlushWritesNothing(): void
    {
        $writeApi = $this->createMock(WriteApi::class);
        $writeApi->expects(self::once())->method('write');

        $client = $this->createMock(Client::class);
        $client->expects(self::once())->method('createWriteApi')->willReturn($writeApi);

        $listener = new InfluxDbLifecycleListener(true, $client, new NullLogger());

        $listener->onResponseMapped($this->responseMapped());
        $listener->flush();
        $listener->flush();
    }

    #[Test]
    public function writeFailuresAreLoggedAndNeverPropagated(): void
    {
        $writeApi = $this->createMock(WriteApi::class);
        $writeApi->method('write')->willThrowException(new \RuntimeException('influx unreachable'));

        $client = $this->createMock(Client::class);
        $client->method('createWriteApi')->willReturn($writeApi);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Failed to write integration lifecycle points to InfluxDB.',
                ['exception' => \RuntimeException::class, 'message' => 'influx unreachable', 'pointCount' => 1],
            );

        $listener = new InfluxDbLifecycleListener(true, $client, $logger);

        $listener->onResponseMapped($this->responseMapped());
        $listener->flush();
    }

    #[Test]
    public function pointUsesTheEventTimestampConvertedToMillisecondPrecision(): void
    {
        $writeApi = $this->createMock(WriteApi::class);
        $writeApi->expects(self::once())
            ->method('write')
            ->with(self::callback(static function (array $points): bool {
                self::assertCount(2, $points);
                self::assertInstanceOf(Point::class, $points[0]);
                self::assertInstanceOf(Point::class, $points[1]);

                // responseMapped()'s 1_700_000_000.1234s * 1000 = 1700000000123.4ms:
                // chosen so round() rounds down, distinguishing it from ceil().
                self::assertStringEndsWith(' 1700000000123', (string) $points[0]->toLineProtocol());
                // requestFailed()'s 1_700_000_001.5006s * 1000 = 1700000001500.6ms:
                // chosen so round() rounds up, distinguishing it from floor().
                self::assertStringEndsWith(' 1700000001501', (string) $points[1]->toLineProtocol());

                return true;
            }), WritePrecision::MS);

        $client = $this->createMock(Client::class);
        $client->method('createWriteApi')->willReturn($writeApi);

        $listener = new InfluxDbLifecycleListener(true, $client, new NullLogger());

        $listener->onResponseMapped($this->responseMapped());
        $listener->onRequestFailed($this->requestFailed());
        $listener->flush();
    }

    #[Test]
    public function requestKeyIsCarriedAsAFieldNotATag(): void
    {
        $writeApi = $this->createMock(WriteApi::class);
        $writeApi->expects(self::once())
            ->method('write')
            ->with(self::callback(static function (array $points): bool {
                self::assertCount(1, $points);
                self::assertInstanceOf(Point::class, $points[0]);
                $lineProtocol = $points[0]->toLineProtocol();
                self::assertIsString($lineProtocol);
                self::assertStringContainsString('request_key="req-123"', $lineProtocol);
                self::assertStringNotContainsString('req-123', explode(' ', $lineProtocol)[0]);

                return true;
            }), WritePrecision::MS);

        $client = $this->createMock(Client::class);
        $client->method('createWriteApi')->willReturn($writeApi);

        $listener = new InfluxDbLifecycleListener(true, $client, new NullLogger());

        $listener->onResponseMapped($this->responseMapped());
        $listener->flush();
    }

    private function responseMapped(): ResponseMapped
    {
        return new ResponseMapped(
            integrationName: 'tmdb',
            action: 'get_movie',
            durationMs: 42.5,
            statusCode: 200,
            responseClass: 'App\Foo',
            timestamp: 1_700_000_000.1234,
            requestKey: 'req-123',
        );
    }

    private function requestFailed(): RequestFailed
    {
        return new RequestFailed(
            integrationName: 'supplier',
            action: 'get_prices',
            durationMs: 15.0,
            statusCode: 503,
            exceptionClass: 'RuntimeException',
            message: 'Connection refused',
            timestamp: 1_700_000_001.5006,
        );
    }
}
