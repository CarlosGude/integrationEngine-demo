<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Integrations\Tmdb;

use IntegrationEngine\Core\Contract\Action\AbstractAction;

final class GetMovieAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'get_movie';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): ?string
    {
        return GetMovieMapper::class;
    }
}
