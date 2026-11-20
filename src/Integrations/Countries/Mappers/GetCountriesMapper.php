<?php

declare(strict_types=1);

namespace App\Integrations\Countries\Mappers;

use App\Integrations\Countries\GetCountries\GetCountriesAction;
use App\Integrations\Countries\GetCountries\GetCountriesResponse;
use App\Shared\Infrastructure\Integration\ResponseFields;
use App\Shared\Infrastructure\Integration\UnexpectedApiResponse;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class GetCountriesMapper extends AbstractMapper
{
    private const SOURCE = 'Countries GraphQL';

    public static function getAction(): string
    {
        return GetCountriesAction::class;
    }

    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        // GraphQLClientAdapter hands the mapper the response's `data` member,
        // not the whole envelope: `errors` has already been turned into an
        // exception by then. The schema declares every field read here non-null.
        $countries = [];

        foreach (ResponseFields::array($response, 'countries', self::SOURCE) as $index => $country) {
            $field = \sprintf('countries[%s]', $index);
            if (!\is_array($country)) {
                throw UnexpectedApiResponse::invalidField(self::SOURCE, $field, 'an object');
            }

            $continent = ResponseFields::array($country, 'continent', self::SOURCE.' '.$field);
            $countries[] = [
                'code' => ResponseFields::string($country, 'code', self::SOURCE.' '.$field),
                'name' => ResponseFields::string($country, 'name', self::SOURCE.' '.$field),
                'continent' => ResponseFields::string($continent, 'name', self::SOURCE.' '.$field.'.continent'),
            ];
        }

        return new GetCountriesResponse($countries);
    }
}
