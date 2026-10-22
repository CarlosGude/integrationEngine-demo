<?php

declare(strict_types=1);

namespace App\Shared\Observability;

/**
 * @phpstan-type TracedCall array{
 *     action: string,
 *     method: string,
 *     status: int,
 *     outcome: string,
 *     duration_ms: float,
 *     upstream_ms: float|null,
 *     mapping_ms: float|null,
 * }
 */
final readonly class CallTrace
{
    /**
     * @param list<TracedCall> $calls
     */
    public function __construct(
        public array $calls,
        public float $totalDurationMs,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'calls' => $this->calls,
            'total_duration_ms' => $this->totalDurationMs,
        ];
    }
}
