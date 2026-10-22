<?php

declare(strict_types=1);

namespace App\Shared\Observability;

/**
 * Collects the calls made while one tour step runs. Entries come from
 * Lifecycle\TraceLifecycleListener, i.e. from the engine's own lifecycle
 * events, so each one is a real integration call with its real status.
 *
 * @phpstan-import-type TracedCall from CallTrace
 */
final class TraceRecorderMiddleware
{
    private static ?float $startedAt = null;

    /** @var list<TracedCall> */
    private static array $calls = [];

    public static function startTrace(): void
    {
        self::$startedAt = microtime(true);
        self::$calls = [];
    }

    /**
     * Ends the trace and returns it, or null if none was started. The total
     * is wall-clock time since startTrace(), not the sum of the calls: the
     * calls of a sendMany() batch overlap, and adding them up would report
     * the batch as if it had run sequentially.
     */
    public static function stopTrace(): ?CallTrace
    {
        if (self::$startedAt === null) {
            return null;
        }

        $trace = new CallTrace(
            calls: self::$calls,
            totalDurationMs: round((microtime(true) - self::$startedAt) * 1000, 2),
        );

        self::$startedAt = null;
        self::$calls = [];

        return $trace;
    }

    public static function recordCall(
        string $action,
        string $method,
        int $status,
        CallOutcome $outcome,
        float $durationMs,
        ?float $upstreamMs = null,
        ?float $mappingMs = null,
    ): void {
        if (self::$startedAt === null) {
            return;
        }

        self::$calls[] = [
            'action' => $action,
            'method' => $method,
            'status' => $status,
            'outcome' => $outcome->value,
            'duration_ms' => round($durationMs, 2),
            'upstream_ms' => $upstreamMs === null ? null : round($upstreamMs, 2),
            'mapping_ms' => $mappingMs === null ? null : round($mappingMs, 2),
        ];
    }
}
