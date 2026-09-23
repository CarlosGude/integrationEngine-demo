<?php

declare(strict_types=1);

namespace App\Catalog\Application;

use App\Catalog\Domain\Movie;
use App\Integrations\Tmdb\GetConfiguration\GetConfigurationResponse;
use App\Integrations\Tmdb\GetMovie\GetMovieResponse;
use App\Integrations\Tmdb\TmdbIntegration;
use IntegrationEngine\Core\Batch\EngineRequest;
use IntegrationEngine\Core\Contract\Action\DefaultActionContext;
use IntegrationEngine\Core\Registry\IntegrationRegistry;

// tour:start solution/separated-responsibilities
final class MovieCatalogGateway
{
    public function __construct(
        private readonly TmdbIntegration $tmdb,
        private readonly IntegrationRegistry $integrationRegistry,
    ) {
    }

    // tour:start solution/loose-coupling
    public function getMovieById(int $movieId): Movie
    {
        $movieResponse = $this->tmdb->getMovie($movieId);
        $configResponse = $this->tmdb->getConfiguration();

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
    // tour:end

    // tour:start solution/parallel-requests
    /**
     * @param array<int> $movieIds
     *
     * @return array<int, Movie|null>
     */
    public function getMoviesByIdBatch(array $movieIds): array
    {
        $engine = $this->integrationRegistry->get('tmdb');

        $requests = [];
        foreach ($movieIds as $movieId) {
            $requests[$movieId] = new EngineRequest(
                actionName: 'get_movie',
                context: DefaultActionContext::create(['movie_id' => $movieId]),
            );
        }

        $results = $engine->sendMany($requests);
        $configResponse = $engine->send('get_configuration');
        \assert($configResponse instanceof GetConfigurationResponse);

        /** @var array<int, Movie|null> $movies */
        $movies = [];
        foreach ($results->responses() as $movieId => $movieResponse) {
            \assert($movieResponse instanceof GetMovieResponse);

            $posterUrl = $this->buildPosterUrl(
                $movieResponse->posterPath(),
                $configResponse->posterSecureBaseUrl(),
                $configResponse->posterSizes(),
            );

            $movies[$movieId] = Movie::fromInfrastructure(
                id: $movieResponse->id(),
                title: $movieResponse->title(),
                overview: $movieResponse->overview(),
                posterPath: $movieResponse->posterPath(),
                voteAverage: $movieResponse->voteAverage(),
                releaseDate: $movieResponse->releaseDate(),
                posterUrl: $posterUrl,
            );
        }

        foreach ($movieIds as $movieId) {
            if (!isset($movies[$movieId])) {
                $movies[$movieId] = null;
            }
        }

        return $movies;
    }
    // tour:end

    /** @param list<string> $sizes */
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
// tour:end
