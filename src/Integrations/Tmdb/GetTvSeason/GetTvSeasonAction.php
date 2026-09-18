<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb\GetTvSeason;

use App\Integrations\Tmdb\Mappers\GetTvSeasonMapper;
use IntegrationEngine\Core\Contract\Action\AbstractAction;

final class GetTvSeasonAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'get_tv_season';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): ?string
    {
        return GetTvSeasonMapper::class;
    }
}
