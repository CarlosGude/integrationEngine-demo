<?php

declare(strict_types=1);

namespace App\Integrations\Countries\GetCountries;

use App\Integrations\Countries\Mappers\GetCountriesMapper;
use IntegrationEngine\Core\Contract\Action\AbstractAction;

final class GetCountriesAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'get_countries';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): string
    {
        return GetCountriesMapper::class;
    }
}
