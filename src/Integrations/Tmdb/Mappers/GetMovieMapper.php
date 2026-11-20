<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb\Mappers;

use App\Integrations\Tmdb\GetMovie\GetMovieAction;
use App\Integrations\Tmdb\GetMovie\GetMovieResponse;
use App\Shared\Infrastructure\Integration\ResponseFields;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class GetMovieMapper extends AbstractMapper
{
    private const SOURCE = 'TMDB get_movie';

    public static function getAction(): string
    {
        return GetMovieAction::class;
    }

    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        return new GetMovieResponse(
            id: ResponseFields::int($response, 'id', self::SOURCE),
            title: ResponseFields::string($response, 'title', self::SOURCE),
            overview: ResponseFields::string($response, 'overview', self::SOURCE),
            // TMDB sends null for movies without a poster.
            posterPath: ResponseFields::stringOrNull($response, 'poster_path', self::SOURCE),
            voteAverage: ResponseFields::number($response, 'vote_average', self::SOURCE),
            releaseDate: ResponseFields::string($response, 'release_date', self::SOURCE),
        );
    }
}
