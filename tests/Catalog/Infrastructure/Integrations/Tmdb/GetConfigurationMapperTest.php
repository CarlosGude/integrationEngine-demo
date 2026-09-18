<?php

declare(strict_types=1);

namespace Tests\Catalog\Infrastructure\Integrations\Tmdb;

use App\Integrations\Tmdb\GetConfiguration\GetConfigurationAction;
use App\Integrations\Tmdb\GetConfiguration\GetConfigurationResponse;
use App\Integrations\Tmdb\Mappers\GetConfigurationMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetConfigurationMapperTest extends TestCase
{
    #[Test]
    public function mapsConfigurationResponse(): void
    {
        $fixture = \json_decode(
            (string) \file_get_contents(__DIR__.'/Fixtures/get-configuration.json'),
            associative: true,
            flags: \JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($fixture);

        $action = GetConfigurationAction::create('GET', '/3/configuration');
        $response = GetConfigurationMapper::map($action, $fixture, []);

        self::assertInstanceOf(GetConfigurationResponse::class, $response);
        self::assertNotEmpty($response->posterSecureBaseUrl());
        self::assertNotEmpty($response->posterSizes());
        self::assertStringStartsWith('https://image.tmdb.org', $response->posterSecureBaseUrl());
    }
}
