<?php

declare(strict_types=1);

namespace Tests\Integrations\Tmdb;

use App\Integrations\Tmdb\GetConfiguration\GetConfigurationResponse;
use App\Integrations\Tmdb\GetMovie\GetMovieResponse;
use App\Integrations\Tmdb\GetTvSeason\GetTvSeasonResponse;
use App\Integrations\Tmdb\TmdbIntegration;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TmdbIntegrationTest extends KernelTestCase
{
    #[Test]
    public function getConfigurationReturnsTheParsedResponse(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $configJson = json_encode([
            'images' => ['secure_base_url' => 'https://image.tmdb.org/t/p/', 'poster_sizes' => ['w500']],
        ], \JSON_THROW_ON_ERROR);
        $container->set('http_client', new MockHttpClient([new MockResponse($configJson, ['http_code' => 200])]));

        $integration = $container->get(TmdbIntegration::class);
        \assert($integration instanceof TmdbIntegration);

        $config = $integration->getConfiguration();

        self::assertInstanceOf(GetConfigurationResponse::class, $config);
        self::assertSame('https://image.tmdb.org/t/p/', $config->posterSecureBaseUrl());
    }

    #[Test]
    public function getMovieReturnsTheParsedResponse(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $movieJson = json_encode([
            'id' => 550,
            'title' => 'Fight Club',
            'overview' => 'Overview',
            'poster_path' => '/poster.jpg',
            'vote_average' => 8.4,
            'release_date' => '1999-10-15',
        ], \JSON_THROW_ON_ERROR);
        $container->set('http_client', new MockHttpClient([new MockResponse($movieJson, ['http_code' => 200])]));

        $integration = $container->get(TmdbIntegration::class);
        \assert($integration instanceof TmdbIntegration);

        $movie = $integration->getMovie(550);

        self::assertInstanceOf(GetMovieResponse::class, $movie);
        self::assertSame(550, $movie->id());
        self::assertSame('Fight Club', $movie->title());
    }

    #[Test]
    public function getTvSeasonReturnsTheParsedResponse(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $seasonJson = json_encode([
            'id' => 3572,
            'name' => 'Season 1',
            'episodes' => [['id' => 1, 'name' => 'Pilot']],
        ], \JSON_THROW_ON_ERROR);
        $container->set('http_client', new MockHttpClient([new MockResponse($seasonJson, ['http_code' => 200])]));

        $integration = $container->get(TmdbIntegration::class);
        \assert($integration instanceof TmdbIntegration);

        $season = $integration->getTvSeason(tvId: 1399, seasonNumber: 1);

        self::assertInstanceOf(GetTvSeasonResponse::class, $season);
        self::assertSame(3572, $season->id());
        self::assertSame('Season 1', $season->name());
        self::assertSame([['id' => 1, 'name' => 'Pilot']], $season->episodes());
    }
}
