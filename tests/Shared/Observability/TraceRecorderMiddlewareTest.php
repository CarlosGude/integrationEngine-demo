<?php

declare(strict_types=1);

namespace Tests\Shared\Observability;

use App\Shared\Observability\TraceRecorderMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TraceRecorderMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        TraceRecorderMiddleware::clearTrace();
    }

    protected function tearDown(): void
    {
        TraceRecorderMiddleware::clearTrace();
    }

    #[Test]
    public function noCurrentTraceBeforeStarted(): void
    {
        self::assertNull(TraceRecorderMiddleware::getCurrentTrace());
    }

    #[Test]
    public function startTraceCreatesAnEmptyTraceWithZeroDuration(): void
    {
        TraceRecorderMiddleware::startTrace();

        $trace = TraceRecorderMiddleware::getCurrentTrace();

        self::assertNotNull($trace);
        self::assertSame([], $trace->calls);
        self::assertSame(0.0, $trace->totalDurationMs);
    }

    #[Test]
    public function recordCallBeforeStartIsANoOp(): void
    {
        TraceRecorderMiddleware::recordCall('get_movie', 'GET', 200, 12.5);

        self::assertNull(TraceRecorderMiddleware::getCurrentTrace());
    }

    #[Test]
    public function recordCallAppendsToTraceAndAccumulatesDuration(): void
    {
        TraceRecorderMiddleware::startTrace();

        TraceRecorderMiddleware::recordCall('get_movie', 'GET', 200, 12.345);
        TraceRecorderMiddleware::recordCall('get_configuration', 'GET', 503, 7.1);

        $trace = TraceRecorderMiddleware::getCurrentTrace();

        self::assertNotNull($trace);
        self::assertCount(2, $trace->calls);
        self::assertSame([
            'action' => 'get_movie',
            'method' => 'GET',
            'status' => 200,
            'duration_ms' => 12.35,
        ], $trace->calls[0]);
        self::assertSame([
            'action' => 'get_configuration',
            'method' => 'GET',
            'status' => 503,
            'duration_ms' => 7.1,
        ], $trace->calls[1]);
        self::assertEqualsWithDelta(19.445, $trace->totalDurationMs, 0.0001);
    }

    #[Test]
    public function clearTraceResetsToNull(): void
    {
        TraceRecorderMiddleware::startTrace();
        TraceRecorderMiddleware::recordCall('get_movie', 'GET', 200, 1.0);

        TraceRecorderMiddleware::clearTrace();

        self::assertNull(TraceRecorderMiddleware::getCurrentTrace());
    }

    #[Test]
    public function callTraceToArrayMatchesItsProperties(): void
    {
        TraceRecorderMiddleware::startTrace();
        TraceRecorderMiddleware::recordCall('get_movie', 'GET', 200, 5.0);

        $trace = TraceRecorderMiddleware::getCurrentTrace();
        self::assertNotNull($trace);

        self::assertSame([
            'calls' => $trace->calls,
            'total_duration_ms' => $trace->totalDurationMs,
        ], $trace->toArray());
    }
}
