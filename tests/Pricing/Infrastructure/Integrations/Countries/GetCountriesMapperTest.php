<?php

declare(strict_types=1);

namespace Tests\Pricing\Infrastructure\Integrations\Countries;

use App\Integrations\Countries\GetCountries\GetCountriesAction;
use App\Integrations\Countries\GetCountries\GetCountriesResponse;
use App\Integrations\Countries\Mappers\GetCountriesMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetCountriesMapperTest extends TestCase
{
    #[Test]
    public function mapsGraphQLCountriesResponse(): void
    {
        // The `data` member only: GraphQLClientAdapter unwraps the envelope
        // before the mapper runs.
        $graphQLResponse = [
            'countries' => [
                [
                    'code' => 'US',
                    'name' => 'United States',
                    'continent' => ['name' => 'North America'],
                ],
                [
                    'code' => 'MX',
                    'name' => 'Mexico',
                    'continent' => ['name' => 'North America'],
                ],
            ],
        ];

        $action = GetCountriesAction::create('POST', '/graphql');
        $response = GetCountriesMapper::map($action, $graphQLResponse, []);

        self::assertInstanceOf(GetCountriesResponse::class, $response);
        $countries = $response->countries();
        self::assertCount(2, $countries);
        self::assertSame('US', $countries[0]['code']);
        self::assertSame('North America', $countries[0]['continent']);
    }

    #[Test]
    public function handlesEmptyCountriesList(): void
    {
        $graphQLResponse = ['countries' => []];

        $action = GetCountriesAction::create('POST', '/graphql');
        $response = GetCountriesMapper::map($action, $graphQLResponse, []);

        self::assertInstanceOf(GetCountriesResponse::class, $response);
        self::assertEmpty($response->countries());
    }

    #[Test]
    public function defaultsMissingFieldsToEmptyStrings(): void
    {
        $graphQLResponse = ['countries' => [['code' => 'FR']]];

        $action = GetCountriesAction::create('POST', '/graphql');
        $response = GetCountriesMapper::map($action, $graphQLResponse, []);
        \assert($response instanceof GetCountriesResponse);

        self::assertSame([['code' => 'FR', 'name' => '', 'continent' => '']], $response->countries());
    }

    #[Test]
    public function handlesAMissingCountriesKeyEntirely(): void
    {
        $action = GetCountriesAction::create('POST', '/graphql');
        $response = GetCountriesMapper::map($action, [], []);
        \assert($response instanceof GetCountriesResponse);

        self::assertEmpty($response->countries());
    }
}
