<?php

declare(strict_types=1);

namespace App\Integrations\Countries\Mappers;

use App\Integrations\Countries\GetCountries\GetCountriesAction;
use App\Integrations\Countries\GetCountries\GetCountriesResponse;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class GetCountriesMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return GetCountriesAction::class;
    }

    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        // GraphQLClientAdapter hands the mapper the response's `data` member,
        // not the whole envelope: `errors` has already been turned into an
        // exception by then.
        /** @var array{countries?: list<array{code?: string, name?: string, continent?: array{name?: string}}>} $response */
        $countries = [];

        if (isset($response['countries'])) {
            foreach ($response['countries'] as $country) {
                $countries[] = [
                    'code' => $country['code'] ?? '',
                    'name' => $country['name'] ?? '',
                    'continent' => $country['continent']['name'] ?? '',
                ];
            }
        }

        return new GetCountriesResponse($countries);
    }
}
