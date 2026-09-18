<?php

declare(strict_types=1);

namespace App\Integration;

use App\Integrations\Tmdb\GetConfiguration\GetConfigurationAction;
use App\Integrations\Tmdb\GetConfiguration\GetConfigurationResponse;
use App\Integrations\Tmdb\GetMovie\GetMovieAction;
use App\Integrations\Tmdb\GetMovie\GetMovieResponse;
use App\Integrations\Tmdb\GetTvSeason\GetTvSeasonAction;
use App\Integrations\Tmdb\GetTvSeason\GetTvSeasonResponse;
use IntegrationEngine\Core\Contract\Action\DefaultActionContext;
use IntegrationEngine\Core\Registry\IntegrationRegistry;

final readonly class TmdbIntegration
{
    public function __construct(
        private IntegrationRegistry $registry,
    ) {
    }

    public function getConfiguration(): GetConfigurationResponse
    {
        $engine = $this->registry->get('tmdb');
        $response = $engine->send('get_configuration');

        return $response;
    }

    public function getMovie(int $movieId): GetMovieResponse
    {
        $engine = $this->registry->get('tmdb');
        $context = new DefaultActionContext(['movie_id' => $movieId]);
        $response = $engine->send('get_movie', $context);

        return $response;
    }

    public function getTvSeason(int $tvId, int $seasonNumber): GetTvSeasonResponse
    {
        $engine = $this->registry->get('tmdb');
        $context = new DefaultActionContext([
            'tv_id' => $tvId,
            'season_number' => $seasonNumber,
        ]);
        $response = $engine->send('get_tv_season', $context);

        return $response;
    }
}
