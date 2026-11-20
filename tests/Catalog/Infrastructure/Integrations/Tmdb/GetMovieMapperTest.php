<?php

declare(strict_types=1);

namespace Tests\Catalog\Infrastructure\Integrations\Tmdb;

use App\Integrations\Tmdb\GetMovie\GetMovieAction;
use App\Integrations\Tmdb\GetMovie\GetMovieResponse;
use App\Integrations\Tmdb\Mappers\GetMovieMapper;
use App\Shared\Infrastructure\Integration\UnexpectedApiResponse;
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

    #[Test]
    public function mapsAMovieWithoutAPosterToANullPosterPath(): void
    {
        $action = GetMovieAction::create('GET', '/3/movie/1');
        $response = GetMovieMapper::map($action, self::movie(['poster_path' => null]), []);
        \assert($response instanceof GetMovieResponse);

        self::assertNull($response->posterPath());
    }

    #[Test]
    public function rejectsAResponseWithoutAnId(): void
    {
        $movie = self::movie();
        unset($movie['id']);

        $this->expectException(UnexpectedApiResponse::class);
        $this->expectExceptionMessage('TMDB get_movie: field "id" is missing or not an integer.');

        GetMovieMapper::map(GetMovieAction::create('GET', '/3/movie/1'), $movie, []);
    }

    #[Test]
    public function rejectsARenamedPosterPathField(): void
    {
        $movie = self::movie();
        unset($movie['poster_path']);
        $movie['posterPath'] = '/poster.jpg';

        $this->expectException(UnexpectedApiResponse::class);
        $this->expectExceptionMessage('TMDB get_movie: field "poster_path" is missing or not a string or null.');

        GetMovieMapper::map(GetMovieAction::create('GET', '/3/movie/1'), $movie, []);
    }

    /**
     * @param array<string, mixed> $overrides
     *
     * @return array<string, mixed>
     */
    private static function movie(array $overrides = []): array
    {
        return $overrides + [
            'id' => 1,
            'title' => 'A movie',
            'overview' => 'An overview',
            'poster_path' => '/poster.jpg',
            'vote_average' => 7,
            'release_date' => '2020-01-01',
        ];
    }
}
