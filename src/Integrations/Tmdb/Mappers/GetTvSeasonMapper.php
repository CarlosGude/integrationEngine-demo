<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb\Mappers;

use App\Integrations\Tmdb\GetTvSeason\GetTvSeasonAction;
use App\Integrations\Tmdb\GetTvSeason\GetTvSeasonResponse;
use App\Shared\Infrastructure\Integration\ResponseFields;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class GetTvSeasonMapper extends AbstractMapper
{
    private const SOURCE = 'TMDB get_tv_season';

    public static function getAction(): string
    {
        return GetTvSeasonAction::class;
    }

    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        return new GetTvSeasonResponse(
            id: ResponseFields::int($response, 'id', self::SOURCE),
            name: ResponseFields::string($response, 'name', self::SOURCE),
            episodes: ResponseFields::array($response, 'episodes', self::SOURCE),
        );
    }
}
