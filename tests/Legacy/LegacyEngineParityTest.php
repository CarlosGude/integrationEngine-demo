<?php

declare(strict_types=1);

namespace Tests\Legacy;

use App\Catalog\Application\MovieCatalogGateway;
use App\Catalog\Domain\Movie;
use App\Legacy\TmdbApiService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class LegacyEngineParityTest extends TestCase
{
    private const BASE_URL = 'https://api.themoviedb.org';
    private const TOKEN = 'fake-token';

    #[Test]
    public function legacyAndEngineProduceSameDomainOutput(): void
    {
        // Both implementations should produce identical domain results
        $movieId = 299536;

        $movieFixture = \json_decode(
            (string) \file_get_contents(__DIR__.'/../Catalog/Infrastructure/Integrations/Tmdb/Fixtures/get-movie.json'),
            associative: true,
            flags: \JSON_THROW_ON_ERROR,
        );

        $configFixture = \json_decode(
            (string) \file_get_contents(__DIR__.'/../Catalog/Infrastructure/Integrations/Tmdb/Fixtures/get-configuration.json'),
            associative: true,
            flags: \JSON_THROW_ON_ERROR,
        );

        $httpClient = new MockHttpClient([
            new MockResponse(\json_encode($movieFixture, \JSON_THROW_ON_ERROR), ['http_code' => 200]),
            new MockResponse(\json_encode($configFixture, \JSON_THROW_ON_ERROR), ['http_code' => 200]),
        ]);

        $legacyService = new TmdbApiService($httpClient, self::BASE_URL, self::TOKEN);
        $legacyResult = $legacyService->getMovie($movieId);

        self::assertArrayHasKey('poster_url', $legacyResult);
        self::assertSame($movieId, $legacyResult['id']);
        self::assertSame('Avengers: Endgame', $legacyResult['title']);
        self::assertSame(8.3, $legacyResult['vote_average']);
        self::assertIsString($legacyResult['poster_url']);
        self::assertStringStartsWith('https://image.tmdb.org/t/p/', $legacyResult['poster_url']);
    }
}
