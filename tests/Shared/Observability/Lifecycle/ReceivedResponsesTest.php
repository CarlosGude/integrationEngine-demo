<?php

declare(strict_types=1);

namespace Tests\Shared\Observability\Lifecycle;

use App\Shared\Observability\Lifecycle\ReceivedResponses;
use IntegrationEngine\Core\Event\ResponseReceived;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ReceivedResponsesTest extends TestCase
{
    #[Test]
    public function takeReturnsTheRememberedEventOnlyOnce(): void
    {
        $received = new ReceivedResponses();
        $event = $this->received('tmdb', 'get_movie', null);

        $received->remember($event);

        self::assertSame($event, $received->take('tmdb', 'get_movie', null));
        self::assertNull($received->take('tmdb', 'get_movie', null));
    }

    #[Test]
    public function batchItemsAreKeptApartByRequestKey(): void
    {
        $received = new ReceivedResponses();
        $first = $this->received('tmdb', 'get_movie', 550);
        $second = $this->received('tmdb', 'get_movie', 278);

        $received->remember($first);
        $received->remember($second);

        self::assertSame($second, $received->take('tmdb', 'get_movie', 278));
        self::assertSame($first, $received->take('tmdb', 'get_movie', 550));
    }

    #[Test]
    public function integrationAndActionArePartOfTheKey(): void
    {
        $received = new ReceivedResponses();
        $received->remember($this->received('tmdb', 'get_movie', null));

        self::assertNull($received->take('stripe', 'get_movie', null));
        self::assertNull($received->take('tmdb', 'get_configuration', null));
        self::assertNotNull($received->take('tmdb', 'get_movie', null));
    }

    #[Test]
    public function keyPartsCannotBleedIntoEachOther(): void
    {
        $keys = [
            ReceivedResponses::key('tmdb', 'get_movie', null),
            ReceivedResponses::key('tmdbget', '_movie', null),
            ReceivedResponses::key('tmdb', 'get_movie1', null),
            ReceivedResponses::key('tmdb', 'get_movie', 1),
        ];

        self::assertSame($keys, array_values(array_unique($keys)));
    }

    #[Test]
    public function mappingMsIsTheGapBetweenTheTwoTimestampsInMilliseconds(): void
    {
        $event = new ResponseReceived('tmdb', 'get_movie', 100.0, 200, 1_700_000_000.250);

        self::assertEqualsWithDelta(1.5, ReceivedResponses::mappingMs($event, 1_700_000_000.2515), 0.001);
    }

    private function received(string $integrationName, string $action, int|string|null $requestKey): ResponseReceived
    {
        return new ResponseReceived($integrationName, $action, 100.0, 200, microtime(true), $requestKey);
    }
}
