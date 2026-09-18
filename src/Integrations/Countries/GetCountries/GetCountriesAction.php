<?php

declare(strict_types=1);

namespace App\Integrations\Countries\GetCountries;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;

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

    public static function mapper(): ?string
    {
        return GetCountriesMapper::class;
    }

    public static function getGraphQLQuery(): string
    {
        return <<<'GRAPHQL'
{
  countries {
    code
    name
    continent {
      name
    }
  }
}
GRAPHQL;
    }
}
