<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb\Catalog;

use IntegrationEngine\Core\Contract\Action\AbstractAction;

final class GetConfigurationAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'get_configuration';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): ?string
    {
        return GetConfigurationMapper::class;
    }
}
