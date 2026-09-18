<?php

declare(strict_types=1);

namespace Tests\Catalog\Infrastructure\Integrations\Tmdb;

use App\Integrations\Tmdb\GetMovie\GetMovieAction;
use App\Integrations\Tmdb\GetMovie\GetMovieResponse;
use App\Integrations\Tmdb\Mappers\GetMovieMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetMovieMapperTest extends TestCase
{
    #[Test]
    public function mapsMovieResponse(): void
    {
        $fixture = \json_decode(
            (string) \file_get_contents(__DIR__.'/Fixtures/get-movie.json'),
            associative: true,
            flags: \JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($fixture);

        $action = GetMovieAction::create('GET', '/3/movie/299536');
        $response = GetMovieMapper::map($action, $fixture, []);

        self::assertInstanceOf(GetMovieResponse::class, $response);
        self::assertSame(299536, $response->id());
        self::assertSame('Avengers: Endgame', $response->title());
        self::assertSame('/or06FN4Hf2tfsWASP2Oa6aSy0l1.jpg', $response->posterPath());
        self::assertSame(8.3, $response->voteAverage());
        self::assertSame('2019-04-26', $response->releaseDate());
    }
}
