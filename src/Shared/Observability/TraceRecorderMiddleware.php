<?php

declare(strict_types=1);

namespace App\Shared\Observability;

final class TraceRecorderMiddleware
{
    private static ?CallTrace $currentTrace = null;

    public static function getCurrentTrace(): ?CallTrace
    {
        return self::$currentTrace;
    }

    public static function startTrace(): void
    {
        self::$currentTrace = new CallTrace(calls: [], totalDurationMs: 0.0);
    }

    public static function clearTrace(): void
    {
        self::$currentTrace = null;
    }

    public static function recordCall(string $actionName, string $method, int $status, float $durationMs): void
    {
        if (self::$currentTrace === null) {
            return;
        }

        $calls = self::$currentTrace->calls;
        $calls[] = [
            'action' => $actionName,
            'method' => $method,
            'status' => $status,
            'duration_ms' => round($durationMs, 2),
        ];

        self::$currentTrace = new CallTrace(
            calls: $calls,
            totalDurationMs: self::$currentTrace->totalDurationMs + $durationMs,
        );
    }
}
