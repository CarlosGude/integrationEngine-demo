<?php

declare(strict_types=1);

namespace App\Shared\Observability\Lifecycle;

use IntegrationEngine\Core\Event\ResponseReceived;

/**
 * Pairs each ResponseReceived with the ResponseMapped or RequestFailed that
 * closes the same call, so a listener can tell upstream time from mapping
 * time, and a mapping failure from a call that never got a response.
 *
 * The engine always closes a received response before it moves on — within
 * send() the two events are back to back, and within sendMany() each item's
 * pair carries its batch requestKey — so every entry is taken almost as soon
 * as it's remembered and the map never grows past one batch.
 *
 * Each listener keeps its own instance: take() removes the entry, so a
 * shared one would let the first listener starve the others.
 */
final class ReceivedResponses
{
    /** @var array<string, ResponseReceived> */
    private array $pending = [];

    public static function key(string $integrationName, string $action, int|string|null $requestKey): string
    {
        return $integrationName."\0".$action."\0".$requestKey;
    }

    public static function mappingMs(ResponseReceived $received, float $closedAt): float
    {
        return ($closedAt - $received->timestamp) * 1000;
    }

    public function remember(ResponseReceived $event): void
    {
        $this->pending[self::key($event->integrationName, $event->action, $event->requestKey)] = $event;
    }

    public function take(string $integrationName, string $action, int|string|null $requestKey): ?ResponseReceived
    {
        $key = self::key($integrationName, $action, $requestKey);
        $received = $this->pending[$key] ?? null;
        unset($this->pending[$key]);

        return $received;
    }
}
