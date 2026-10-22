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
use IntegrationEngine\Core\Event\ResponseReceived;
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

    #[Test]
    public function aMappedPointCarriesTheMappingTimeWhenTheResponseWasReceivedFirst(): void
    {
        $lineProtocol = $this->writtenLineProtocol(static function (InfluxDbLifecycleListener $listener): void {
            $listener->onResponseReceived(new ResponseReceived('tmdb', 'get_movie', 40.0, 200, 1_700_000_000.1209, 'req-123'));
            $listener->onResponseMapped(new ResponseMapped('tmdb', 'get_movie', 42.5, 200, 'App\Foo', 1_700_000_000.1234, 'req-123'));
        });

        self::assertMatchesRegularExpression('/mapping_ms=2\.5\d*/', $lineProtocol[0]);
    }

    #[Test]
    public function aFailedPointSaysWhetherTheApiHadAnswered(): void
    {
        $lineProtocol = $this->writtenLineProtocol(function (InfluxDbLifecycleListener $listener): void {
            $listener->onResponseReceived(new ResponseReceived('tmdb', 'get_movie', 40.0, 200, microtime(true)));
            $listener->onRequestFailed(new RequestFailed('tmdb', 'get_movie', 41.0, 0, 'TypeError', 'Integration request failed.', microtime(true)));
            $listener->onRequestFailed($this->requestFailed());
        });

        self::assertStringContainsString('mapping_failed=true', $lineProtocol[0]);
        self::assertStringContainsString('mapping_failed=false', $lineProtocol[1]);
    }

    #[Test]
    public function disabledListenerDoesNotTrackReceivedResponses(): void
    {
        $client = $this->createMock(Client::class);
        $client->expects(self::never())->method('createWriteApi');

        $listener = new InfluxDbLifecycleListener(false, $client, new NullLogger());

        $listener->onResponseReceived(new ResponseReceived('tmdb', 'get_movie', 40.0, 200, microtime(true)));
        $listener->flush();
    }

    /**
     * @param callable(InfluxDbLifecycleListener): void $emit
     *
     * @return list<string>
     */
    private function writtenLineProtocol(callable $emit): array
    {
        $written = [];
        $writeApi = $this->createMock(WriteApi::class);
        $writeApi->expects(self::once())
            ->method('write')
            ->with(self::callback(static function (array $points) use (&$written): bool {
                foreach ($points as $point) {
                    self::assertInstanceOf(Point::class, $point);
                    $written[] = (string) $point->toLineProtocol();
                }

                return true;
            }), WritePrecision::MS);

        $client = $this->createMock(Client::class);
        $client->method('createWriteApi')->willReturn($writeApi);

        $listener = new InfluxDbLifecycleListener(true, $client, new NullLogger());
        $emit($listener);
        $listener->flush();

        return $written;
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
