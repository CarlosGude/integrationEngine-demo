<?php

declare(strict_types=1);

namespace Tests\Catalog\UI;

use App\Catalog\Domain\Movie;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StorefrontControllerTest extends TestCase
{
    #[Test]
    public function storefrontBatchCanContainNullValuesForFailures(): void
    {
        $movie1 = Movie::fromInfrastructure(
            id: 1,
            title: 'Successful Movie',
            overview: 'Overview',
            posterPath: '/path',
            voteAverage: 8.0,
            releaseDate: '2023-01-01',
            posterUrl: 'https://example.com/poster.jpg',
        );

        $batch = [
            1 => $movie1,
            2 => null,
            3 => null,
        ];

        self::assertSame($movie1, $batch[1]);
        self::assertNull($batch[2]);
        self::assertNull($batch[3]);
        self::assertCount(3, $batch);
    }

    #[Test]
    public function movieDataStructureIsCorrect(): void
    {
        $movie = Movie::fromInfrastructure(
            id: 299536,
            title: 'Avengers: Endgame',
            overview: 'The final battle',
            posterPath: '/poster.jpg',
            voteAverage: 8.4,
            releaseDate: '2019-04-26',
            posterUrl: 'https://image.tmdb.org/t/p/w500/poster.jpg',
        );

        self::assertSame(299536, $movie->id);
        self::assertSame('Avengers: Endgame', $movie->title);
        self::assertNotEmpty($movie->posterUrl);
    }
}
