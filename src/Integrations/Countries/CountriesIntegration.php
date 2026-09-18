<?php

declare(strict_types=1);

namespace App\Integrations\Countries;

use App\Integrations\Countries\GetCountries\GetCountriesResponse;
use IntegrationEngine\Core\Registry\IntegrationRegistry;

final readonly class CountriesIntegration
{
    public function __construct(
        private IntegrationRegistry $registry,
    ) {
    }

    public function getCountries(): GetCountriesResponse
    {
        $engine = $this->registry->get('countries');
        $response = $engine->send('get_countries');

        return $response;
    }
}
