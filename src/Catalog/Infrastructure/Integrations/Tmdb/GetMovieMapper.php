<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Integrations\Tmdb;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class GetMovieMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return GetMovieAction::class;
    }

    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        /** @var array{id: int, title: string, overview: string, poster_path: string, vote_average: float, release_date: string} $response */
        return new GetMovieResponse(
            id: $response['id'],
            title: $response['title'],
            overview: $response['overview'],
            posterPath: $response['poster_path'],
            voteAverage: $response['vote_average'],
            releaseDate: $response['release_date'],
        );
    }
}
