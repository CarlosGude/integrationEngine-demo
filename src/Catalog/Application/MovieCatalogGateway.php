<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Movie;
use App\Catalog\Infrastructure\Integrations\Tmdb\GetConfigurationResponse;
use App\Catalog\Infrastructure\Integrations\Tmdb\GetMovieResponse;
use IntegrationEngine\Core\Contract\Context\DefaultActionContext;
use IntegrationEngine\Core\Registry\IntegrationRegistry;

final class MovieCatalogGateway
{
    public function __construct(
        private readonly IntegrationRegistry $integrationRegistry,
    ) {
    }

    public function getMovieById(int $movieId): Movie
    {
        $engine = $this->integrationRegistry->get('tmdb');

        $movieResponse = $engine->send(
            'get_movie',
            context: new DefaultActionContext(['movie_id' => $movieId]),
        );

        \assert($movieResponse instanceof GetMovieResponse);

        $configResponse = $engine->send('get_configuration');
        \assert($configResponse instanceof GetConfigurationResponse);

        $posterUrl = $this->buildPosterUrl(
            $movieResponse->posterPath(),
            $configResponse->posterSecureBaseUrl(),
            $configResponse->posterSizes(),
        );

        return Movie::fromInfrastructure(
            id: $movieResponse->id(),
            title: $movieResponse->title(),
            overview: $movieResponse->overview(),
            posterPath: $movieResponse->posterPath(),
            voteAverage: $movieResponse->voteAverage(),
            releaseDate: $movieResponse->releaseDate(),
            posterUrl: $posterUrl,
        );
    }

    private function buildPosterUrl(string $posterPath, string $secureBaseUrl, array $sizes): string
    {
        if (empty($posterPath)) {
            return '';
        }

        $preferredSize = 'w500';
        if (!\in_array($preferredSize, $sizes, true)) {
            $preferredSize = $sizes[0] ?? 'original';
        }

        return $secureBaseUrl.$preferredSize.$posterPath;
    }
}
