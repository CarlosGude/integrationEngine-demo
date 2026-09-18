<?php

declare(strict_types=1);

namespace Tests\Catalog\Infrastructure\Integrations\Tmdb;

use App\Integrations\Tmdb\Catalog\GetTvSeasonAction;
use App\Integrations\Tmdb\Catalog\GetTvSeasonMapper;
use App\Integrations\Tmdb\Catalog\GetTvSeasonResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GetTvSeasonMapperTest extends TestCase
{
    #[Test]
    public function mapsTvSeasonResponse(): void
    {
        $fixture = \json_decode(
            \file_get_contents(__DIR__.'/Fixtures/get-tv-season.json'),
            associative: true,
        );

        $action = GetTvSeasonAction::create('GET', '/3/tv/1/season/1');
        $response = GetTvSeasonMapper::map($action, $fixture, []);

        self::assertInstanceOf(GetTvSeasonResponse::class, $response);
        self::assertSame(1, $response->id());
        self::assertSame('Season 1', $response->name());
        self::assertNotEmpty($response->episodes());
    }
}
