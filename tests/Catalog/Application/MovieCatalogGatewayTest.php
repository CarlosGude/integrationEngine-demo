<?php

declare(strict_types=1);

namespace Tests\Catalog\Application;

use App\Catalog\Application\MovieCatalogGateway;
use App\Catalog\Domain\Movie;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class MovieCatalogGatewayTest extends KernelTestCase
{
    #[Test]
    public function getMovieByIdBuildsMovieDomainObject(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $movieData = \json_encode([
            'id' => 550,
            'title' => 'Fight Club',
            'overview' => 'An insomniac office worker and a devil-may-care soapmaker form an underground fight club.',
            'poster_path' => '/pB8BM8DQsVv0AT39VHkVrNg8OVV.jpg',
            'vote_average' => 8.8,
            'release_date' => '1999-10-15',
        ]);
        \assert($movieData !== false);

        $configData = \json_encode([
            'images' => [
                'secure_base_url' => 'https://image.tmdb.org/t/p/',
                'poster_sizes' => ['w92', 'w154', 'w185', 'w342', 'w500', 'w780', 'original'],
            ],
        ]);
        \assert($configData !== false);

        $responses = [
            new MockResponse($movieData, ['http_code' => 200]),
            new MockResponse($configData, ['http_code' => 200]),
        ];

        $mockClient = new MockHttpClient($responses);
        $container->set('http_client', $mockClient);

        $gateway = $container->get(MovieCatalogGateway::class);
        \assert($gateway instanceof MovieCatalogGateway);
        $movie = $gateway->getMovieById(550);

        self::assertInstanceOf(Movie::class, $movie);
        self::assertSame(550, $movie->id);
        self::assertSame('Fight Club', $movie->title);
        self::assertSame(8.8, $movie->voteAverage);
        self::assertStringContainsString('pB8BM8DQsVv0AT39VHkVrNg8OVV', $movie->posterUrl);
    }

    #[Test]
    public function getMoviesByIdBatchHandlesNullValuesForFailures(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $movieData = \json_encode([
            'id' => 550,
            'title' => 'Fight Club',
            'overview' => 'Overview',
            'poster_path' => '/path.jpg',
            'vote_average' => 8.8,
            'release_date' => '1999-10-15',
        ]);
        \assert($movieData !== false);

        $configData = \json_encode([
            'images' => [
                'secure_base_url' => 'https://image.tmdb.org/t/p/',
                'poster_sizes' => ['w500', 'original'],
            ],
        ]);
        \assert($configData !== false);

        $responses = [
            new MockResponse($movieData, ['http_code' => 200]),
            new MockResponse('', ['http_code' => 404]),
            new MockResponse('', ['http_code' => 404]),
            new MockResponse($configData, ['http_code' => 200]),
        ];

        $mockClient = new MockHttpClient($responses);
        $container->set('http_client', $mockClient);

        $gateway = $container->get(MovieCatalogGateway::class);
        \assert($gateway instanceof MovieCatalogGateway);
        $batch = $gateway->getMoviesByIdBatch([550, 999, 1000]);

        self::assertCount(3, $batch);
        self::assertInstanceOf(Movie::class, $batch[550]);
        self::assertSame('Fight Club', $batch[550]->title);
        self::assertNull($batch[999]);
        self::assertNull($batch[1000]);
    }
}
