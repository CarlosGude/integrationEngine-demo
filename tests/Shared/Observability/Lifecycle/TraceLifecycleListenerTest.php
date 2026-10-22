<?php

declare(strict_types=1);

namespace Tests\Shared\Observability\Lifecycle;

use App\Shared\Observability\Lifecycle\TraceLifecycleListener;
use App\Shared\Observability\TraceRecorderMiddleware;
use IntegrationEngine\Core\Event\RequestFailed;
use IntegrationEngine\Core\Event\RequestSent;
use IntegrationEngine\Core\Event\ResponseMapped;
use IntegrationEngine\Core\Event\ResponseReceived;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TraceLifecycleListenerTest extends TestCase
{
    protected function setUp(): void
    {
        TraceRecorderMiddleware::startTrace();
    }

    protected function tearDown(): void
    {
        TraceRecorderMiddleware::stopTrace();
    }

    #[Test]
    public function aMappedCallIsTracedWithUpstreamAndMappingTimeSplit(): void
    {
        $listener = new TraceLifecycleListener();

        $listener->onRequestSent(new RequestSent('tmdb', 'get_movie', 'GET', '/3/movie/{movie_id}', 1_700_000_000.0));
        $listener->onResponseReceived(new ResponseReceived('tmdb', 'get_movie', 120.0, 200, 1_700_000_000.120));
        $listener->onResponseMapped(new ResponseMapped('tmdb', 'get_movie', 122.5, 200, 'App\Foo', 1_700_000_000.1225));

        self::assertSame([[
            'action' => 'tmdb.get_movie',
            'method' => 'GET',
            'status' => 200,
            'outcome' => 'mapped',
            'duration_ms' => 122.5,
            'upstream_ms' => 120.0,
            'mapping_ms' => 2.5,
        ]], $this->calls());
    }

    #[Test]
    public function aResponseThatFailsToMapKeepsTheStatusTheApiAnsweredWith(): void
    {
        $listener = new TraceLifecycleListener();

        $listener->onRequestSent(new RequestSent('tmdb', 'get_movie', 'GET', '/3/movie/{movie_id}', 1_700_000_000.0));
        $listener->onResponseReceived(new ResponseReceived('tmdb', 'get_movie', 120.0, 200, 1_700_000_000.120));
        $listener->onRequestFailed($this->failed('tmdb', 'get_movie', 0));

        $calls = $this->calls();
        self::assertCount(1, $calls);
        self::assertSame(200, $calls[0]['status']);
        self::assertSame('mapping_failed', $calls[0]['outcome']);
        self::assertSame(120.0, $calls[0]['upstream_ms']);
        self::assertNull($calls[0]['mapping_ms']);
    }

    #[Test]
    public function aCallThatNeverGotAResponseIsTracedAsFailed(): void
    {
        $listener = new TraceLifecycleListener();

        $listener->onRequestSent(new RequestSent('tmdb', 'get_movie', 'GET', '/3/movie/{movie_id}', 1_700_000_000.0));
        $listener->onRequestFailed($this->failed('tmdb', 'get_movie', 401));

        $calls = $this->calls();
        self::assertCount(1, $calls);
        self::assertSame('GET', $calls[0]['method']);
        self::assertSame(401, $calls[0]['status']);
        self::assertSame('failed', $calls[0]['outcome']);
        self::assertNull($calls[0]['upstream_ms']);
    }

    #[Test]
    public function eachBatchItemIsItsOwnEntry(): void
    {
        $listener = new TraceLifecycleListener();

        foreach ([550, 278] as $key) {
            $listener->onRequestSent(new RequestSent('tmdb', 'get_movie', 'GET', '/3/movie/{movie_id}', 1_700_000_000.0, requestKey: $key));
        }
        $listener->onResponseReceived(new ResponseReceived('tmdb', 'get_movie', 300.0, 200, 1_700_000_000.300, 550));
        $listener->onResponseMapped(new ResponseMapped('tmdb', 'get_movie', 301.0, 200, 'App\Foo', 1_700_000_000.301, 550));
        $listener->onResponseReceived(new ResponseReceived('tmdb', 'get_movie', 302.0, 404, 1_700_000_000.302, 278));
        $listener->onRequestFailed($this->failed('tmdb', 'get_movie', 0, 278));

        $calls = $this->calls();
        self::assertCount(2, $calls);
        self::assertSame(['mapped', 'mapping_failed'], array_column($calls, 'outcome'));
        self::assertSame([200, 404], array_column($calls, 'status'));
        self::assertSame(['GET', 'GET'], array_column($calls, 'method'));
    }

    #[Test]
    public function nothingIsRecordedOutsideATourRun(): void
    {
        TraceRecorderMiddleware::stopTrace();
        $listener = new TraceLifecycleListener();

        $listener->onRequestSent(new RequestSent('tmdb', 'get_movie', 'GET', '/3/movie/{movie_id}', 1_700_000_000.0));
        $listener->onResponseMapped(new ResponseMapped('tmdb', 'get_movie', 1.0, 200, 'App\Foo', 1_700_000_000.001));

        self::assertNull(TraceRecorderMiddleware::stopTrace());
    }

    private function failed(string $integrationName, string $action, int $statusCode, int|string|null $requestKey = null): RequestFailed
    {
        return new RequestFailed($integrationName, $action, 125.0, $statusCode, 'RuntimeException', 'Integration request failed.', 1_700_000_000.125, $requestKey);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function calls(): array
    {
        $trace = TraceRecorderMiddleware::stopTrace();
        self::assertNotNull($trace);

        return $trace->calls;
    }
}
