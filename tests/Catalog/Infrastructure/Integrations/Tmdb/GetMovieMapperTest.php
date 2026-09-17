<?php

declare(strict_types=1);

namespace Tests\Catalog\Infrastructure\Integrations\Tmdb;

use App\Catalog\Infrastructure\Integrations\Tmdb\GetMovieAction;
use App\Catalog\Infrastructure\Integrations\Tmdb\GetMovieMapper;
use App\Catalog\Infrastructure\Integrations\Tmdb\GetMovieResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetMovieMapperTest extends TestCase
{
    #[Test]
    public function mapsMovieResponse(): void
    {
        $fixture = \json_decode(
            \file_get_contents(__DIR__.'/Fixtures/get-movie.json'),
            associative: true,
        );

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
