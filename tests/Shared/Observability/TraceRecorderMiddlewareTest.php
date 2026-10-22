<?php

declare(strict_types=1);

namespace Tests\Shared\Observability;

use App\Shared\Observability\CallOutcome;
use App\Shared\Observability\TraceRecorderMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TraceRecorderMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        TraceRecorderMiddleware::stopTrace();
    }

    protected function tearDown(): void
    {
        TraceRecorderMiddleware::stopTrace();
    }

    #[Test]
    public function stopTraceWithoutStartReturnsNull(): void
    {
        self::assertNull(TraceRecorderMiddleware::stopTrace());
    }

    #[Test]
    public function startedTraceWithNoCallsIsEmpty(): void
    {
        TraceRecorderMiddleware::startTrace();

        $trace = TraceRecorderMiddleware::stopTrace();

        self::assertNotNull($trace);
        self::assertSame([], $trace->calls);
        self::assertGreaterThanOrEqual(0.0, $trace->totalDurationMs);
    }

    #[Test]
    public function recordCallBeforeStartIsANoOp(): void
    {
        TraceRecorderMiddleware::recordCall('tmdb.get_movie', 'GET', 200, CallOutcome::Mapped, 12.5);
        TraceRecorderMiddleware::startTrace();

        $trace = TraceRecorderMiddleware::stopTrace();

        self::assertNotNull($trace);
        self::assertSame([], $trace->calls);
    }

    #[Test]
    public function recordCallAppendsRoundedEntriesInOrder(): void
    {
        TraceRecorderMiddleware::startTrace();

        TraceRecorderMiddleware::recordCall('tmdb.get_movie', 'GET', 200, CallOutcome::Mapped, 12.345, 12.006, 0.344);
        TraceRecorderMiddleware::recordCall('supplier.get_prices', 'GET', 503, CallOutcome::Failed, 7.1);

        $trace = TraceRecorderMiddleware::stopTrace();

        self::assertNotNull($trace);
        self::assertSame([
            [
                'action' => 'tmdb.get_movie',
                'method' => 'GET',
                'status' => 200,
                'outcome' => 'mapped',
                'duration_ms' => 12.35,
                'upstream_ms' => 12.01,
                'mapping_ms' => 0.34,
            ],
            [
                'action' => 'supplier.get_prices',
                'method' => 'GET',
                'status' => 503,
                'outcome' => 'failed',
                'duration_ms' => 7.1,
                'upstream_ms' => null,
                'mapping_ms' => null,
            ],
        ], $trace->calls);
    }

    #[Test]
    public function totalIsWallClockTimeNotTheSumOfOverlappingCalls(): void
    {
        TraceRecorderMiddleware::startTrace();

        // Five batch items that each report the whole batch's 1000ms.
        for ($i = 0; $i < 5; ++$i) {
            TraceRecorderMiddleware::recordCall('tmdb.get_movie', 'GET', 200, CallOutcome::Mapped, 1000.0);
        }

        $trace = TraceRecorderMiddleware::stopTrace();

        self::assertNotNull($trace);
        self::assertLessThan(1000.0, $trace->totalDurationMs);
    }

    #[Test]
    public function stopTraceEndsRecording(): void
    {
        TraceRecorderMiddleware::startTrace();
        TraceRecorderMiddleware::stopTrace();

        TraceRecorderMiddleware::recordCall('tmdb.get_movie', 'GET', 200, CallOutcome::Mapped, 1.0);

        self::assertNull(TraceRecorderMiddleware::stopTrace());
    }

    #[Test]
    public function startTraceDiscardsCallsFromAnUnstoppedTrace(): void
    {
        TraceRecorderMiddleware::startTrace();
        TraceRecorderMiddleware::recordCall('tmdb.get_movie', 'GET', 200, CallOutcome::Mapped, 1.0);

        TraceRecorderMiddleware::startTrace();
        $trace = TraceRecorderMiddleware::stopTrace();

        self::assertNotNull($trace);
        self::assertSame([], $trace->calls);
    }

    #[Test]
    public function callTraceToArrayMatchesItsProperties(): void
    {
        TraceRecorderMiddleware::startTrace();
        TraceRecorderMiddleware::recordCall('tmdb.get_movie', 'GET', 200, CallOutcome::Mapped, 5.0);

        $trace = TraceRecorderMiddleware::stopTrace();
        self::assertNotNull($trace);

        self::assertSame([
            'calls' => $trace->calls,
            'total_duration_ms' => $trace->totalDurationMs,
        ], $trace->toArray());
    }
}
