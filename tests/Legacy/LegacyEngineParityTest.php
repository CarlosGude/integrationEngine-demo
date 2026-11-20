<?php

declare(strict_types=1);

namespace Tests\Legacy;

use App\Catalog\Application\MovieCatalogGateway;
use App\Legacy\TmdbApiService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Feeds the same TMDB responses to the legacy service and to the engine-based
 * gateway, and requires both to produce the same movie.
 */
final class LegacyEngineParityTest extends KernelTestCase
{
    private const FIXTURES = __DIR__.'/../Catalog/Infrastructure/Integrations/Tmdb/Fixtures/';

    /** @return iterable<string, array{array<string, mixed>}> */
    public static function movies(): iterable
    {
        $movie = self::fixture('get-movie.json');

        yield 'movie with a poster' => [$movie];
        yield 'movie without a poster (TMDB sends null)' => [['poster_path' => null] + $movie];
    }

    /** @param array<string, mixed> $movie */
    #[Test]
    #[DataProvider('movies')]
    public function legacyAndEngineProduceTheSameMovie(array $movie): void
    {
        $configuration = self::fixture('get-configuration.json');
        $movieId = $movie['id'];
        self::assertIsInt($movieId);

        $legacy = (new TmdbApiService(self::tmdbReturning($movie, $configuration), 'https://api.themoviedb.org', 'fake-token'))
            ->getMovie($movieId);

        self::bootKernel();
        $container = self::getContainer();
        $container->set('http_client', self::tmdbReturning($movie, $configuration));
        $gateway = $container->get(MovieCatalogGateway::class);
        \assert($gateway instanceof MovieCatalogGateway);
        $engineMovie = $gateway->getMovieById($movieId);

        self::assertSame($legacy, [
            'id' => $engineMovie->id,
            'title' => $engineMovie->title,
            'overview' => $engineMovie->overview,
            'poster_path' => $engineMovie->posterPath,
            'vote_average' => $engineMovie->voteAverage,
            'release_date' => $engineMovie->releaseDate,
            'poster_url' => $engineMovie->posterUrl,
        ]);
    }

    /**
     * Both implementations request the movie first and the configuration second.
     *
     * @param array<string, mixed> $movie
     * @param array<string, mixed> $configuration
     */
    private static function tmdbReturning(array $movie, array $configuration): MockHttpClient
    {
        return new MockHttpClient([
            new MockResponse(\json_encode($movie, \JSON_THROW_ON_ERROR), ['http_code' => 200]),
            new MockResponse(\json_encode($configuration, \JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]);
    }

    /** @return array<string, mixed> */
    private static function fixture(string $file): array
    {
        $data = \json_decode((string) \file_get_contents(self::FIXTURES.$file), associative: true, flags: \JSON_THROW_ON_ERROR);
        \assert(\is_array($data));

        /** @var array<string, mixed> $data */
        return $data;
    }
}
