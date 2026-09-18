<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb\Mappers;

use App\Integrations\Tmdb\GetTvSeason\GetTvSeasonAction;
use App\Integrations\Tmdb\GetTvSeason\GetTvSeasonResponse;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class GetTvSeasonMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return GetTvSeasonAction::class;
    }

    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        /** @var array{id: int, name: string, episodes: array<string, mixed>} $response */
        return new GetTvSeasonResponse(
            id: $response['id'],
            name: $response['name'],
            episodes: $response['episodes'] ?? [],
        );
    }
}
