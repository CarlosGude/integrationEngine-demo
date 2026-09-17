<?php

declare(strict_types=1);

namespace Tests\Catalog\Application;

use App\Catalog\Application\MovieCatalogGateway;
use App\Catalog\Domain\Movie;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class MovieCatalogGatewayTest extends TestCase
{
    #[Test]
    public function getMovieByIdBuildsMovieDomainObject(): void
    {
        self::markTestSkipped('Requires integration engine mock setup');
    }

    #[Test]
    public function missingLanguagePlaceholderThrowsError(): void
    {
        self::markTestSkipped('Requires integration engine context validation');
    }
}
