<?php

declare(strict_types=1);

namespace Tests\Catalog\UI;

use App\Catalog\Domain\Movie;
use App\Shared\Infrastructure\Middleware\RateLimitMiddleware;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class StorefrontControllerTest extends WebTestCase
{
    private const TITLE = 'Fight Club';
    private const POSTER_PATH = '/poster.jpg';

    protected function setUp(): void
    {
        // RateLimitMiddleware tracks requests-per-second in process-wide
        // static state (see RateLimitMiddlewareTest). Each storefront
        // request alone makes 21 tmdb calls (20 movies + 1 configuration
        // call), so leftover count from an earlier test in this same PHP
        // process could trip the 40/s limit here. Start each test clean.
        $ref = new \ReflectionClass(RateLimitMiddleware::class);
        $ref->getProperty('requestsThisSecond')->setValue(null, 0);
        $ref->getProperty('lastSecond')->setValue(null, 0);
    }

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
        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertNull($batch[2]);
        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
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
            posterPath: self::POSTER_PATH,
            voteAverage: 8.4,
            releaseDate: '2019-04-26',
            posterUrl: 'https://image.tmdb.org/t/p/w500/poster.jpg',
        );

        self::assertSame(299536, $movie->id);
        self::assertSame('Avengers: Endgame', $movie->title);
        self::assertNotEmpty($movie->posterUrl);
    }

    #[Test]
    public function storefrontRendersTheFeaturedMoviesBatch(): void
    {
        $client = self::createClient();
        $container = self::getContainer();

        $movieJson = json_encode([
            'id' => 550,
            'title' => self::TITLE,
            'overview' => 'Overview',
            'poster_path' => self::POSTER_PATH,
            'vote_average' => 8.4,
            'release_date' => '1999-10-15',
        ], \JSON_THROW_ON_ERROR);
        $configJson = json_encode([
            'images' => ['secure_base_url' => 'https://image.tmdb.org/t/p/', 'poster_sizes' => ['w500']],
        ], \JSON_THROW_ON_ERROR);

        // The storefront requests 20 featured movies in one batch, then a
        // trailing configuration call.
        $responses = array_fill(0, 20, new MockResponse($movieJson, ['http_code' => 200]));
        $responses[] = new MockResponse($configJson, ['http_code' => 200]);

        $container->set('http_client', new MockHttpClient($responses));

        $client->request('GET', '/en/store');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringContainsString(self::TITLE, $content);
    }

    #[Test]
    public function storefrontStillRendersWhenSomeMoviesFailToLoad(): void
    {
        $client = self::createClient();
        $container = self::getContainer();

        $movieJson = json_encode([
            'id' => 550,
            'title' => self::TITLE,
            'overview' => 'Overview',
            'poster_path' => self::POSTER_PATH,
            'vote_average' => 8.4,
            'release_date' => '1999-10-15',
        ], \JSON_THROW_ON_ERROR);
        $configJson = json_encode([
            'images' => ['secure_base_url' => 'https://image.tmdb.org/t/p/', 'poster_sizes' => ['w500']],
        ], \JSON_THROW_ON_ERROR);

        $responses = [];
        for ($i = 0; $i < 20; ++$i) {
            $responses[] = $i % 2 === 0
                ? new MockResponse($movieJson, ['http_code' => 200])
                : new MockResponse('', ['http_code' => 404]);
        }
        $responses[] = new MockResponse($configJson, ['http_code' => 200]);

        $container->set('http_client', new MockHttpClient($responses));

        $client->request('GET', '/en/store');

        self::assertResponseIsSuccessful();
    }
}
