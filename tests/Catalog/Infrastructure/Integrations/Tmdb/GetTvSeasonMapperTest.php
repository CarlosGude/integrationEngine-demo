<?php

declare(strict_types=1);

namespace Tests\Catalog\Infrastructure\Integrations\Tmdb;

use App\Integrations\Tmdb\GetTvSeason\GetTvSeasonAction;
use App\Integrations\Tmdb\GetTvSeason\GetTvSeasonResponse;
use App\Integrations\Tmdb\Mappers\GetTvSeasonMapper;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetTvSeasonMapperTest extends TestCase
{
    #[Test]
    public function mapsTvSeasonResponse(): void
    {
        $fixture = \json_decode(
            (string) \file_get_contents(__DIR__.'/Fixtures/get-tv-season.json'),
            associative: true,
            flags: \JSON_THROW_ON_ERROR,
        );
        self::assertIsArray($fixture);

        $action = GetTvSeasonAction::create('GET', '/3/tv/1/season/1');
        $response = GetTvSeasonMapper::map($action, $fixture, []);

        self::assertInstanceOf(GetTvSeasonResponse::class, $response);
        self::assertSame(1, $response->id());
        self::assertSame('Season 1', $response->name());
        self::assertNotEmpty($response->episodes());
    }
}
