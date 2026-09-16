<?php

declare(strict_types=1);

namespace Tests\Catalog\Infrastructure\Integrations\Tmdb;

use App\Catalog\Infrastructure\Integrations\Tmdb\GetConfigurationAction;
use App\Catalog\Infrastructure\Integrations\Tmdb\GetConfigurationMapper;
use App\Catalog\Infrastructure\Integrations\Tmdb\GetConfigurationResponse;
use PHPUnit\Framework\TestCase;

final class GetConfigurationMapperTest extends TestCase
{
    private GetConfigurationMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new GetConfigurationMapper();
    }

    public function test_maps_tmdb_configuration_response(): void
    {
        $body = [
            'images' => [
                'base_url' => 'http://image.tmdb.org/t/p/',
                'secure_base_url' => 'https://image.tmdb.org/t/p/',
                'poster_sizes' => ['w92', 'w154', 'w185', 'w342', 'w500', 'w780', 'original'],
                'profile_sizes' => ['w45', 'w185', 'h632', 'original'],
            ],
            'change_keys' => ['adult', 'air_date', 'budget'],
        ];
        $headers = [];

        $response = $this->mapper->map(new GetConfigurationAction(), $body, $headers);

        $this->assertInstanceOf(GetConfigurationResponse::class, $response);
        $this->assertSame('https://image.tmdb.org/t/p/', $response->posterBaseUrl);
        $this->assertSame(['w92', 'w154', 'w185', 'w342', 'w500', 'w780', 'original'], $response->posterSizes);
    }

    public function test_action_is_correct(): void
    {
        $this->assertSame(GetConfigurationAction::class, $this->mapper->getAction());
    }
}
