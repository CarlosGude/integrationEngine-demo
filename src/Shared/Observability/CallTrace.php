<?php

declare(strict_types=1);

namespace App\Shared\Observability;

final readonly class CallTrace
{
    /**
     * @param list<array{action: string, method: string, status: int, duration_ms: float}> $calls
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
