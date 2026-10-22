<?php

declare(strict_types=1);

namespace Tests\Integrations\Countries;

use App\Integrations\Countries\CountriesIntegration;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Goes through the real GraphQLClientAdapter, which unit-testing the mapper
 * alone can't: the adapter requires a GraphQLBodyInterface body and hands
 * the mapper the response's `data` member, not the whole envelope.
 */
final class CountriesIntegrationTest extends KernelTestCase
{
    #[Test]
    public function getCountriesSendsTheQueryAndMapsTheCountries(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $sentBody = null;
        $container->set('http_client', new MockHttpClient(static function (string $method, string $url, array $options) use (&$sentBody): MockResponse {
            $sentBody = $options['body'] ?? null;

            return new MockResponse((string) json_encode(['data' => ['countries' => [
                ['code' => 'ES', 'name' => 'Spain', 'continent' => ['name' => 'Europe']],
                ['code' => 'MX', 'name' => 'Mexico', 'continent' => ['name' => 'North America']],
            ]]]), ['http_code' => 200]);
        }));

        $integration = $container->get(CountriesIntegration::class);
        \assert($integration instanceof CountriesIntegration);

        $countries = $integration->getCountries()->countries();

        self::assertSame([
            ['code' => 'ES', 'name' => 'Spain', 'continent' => 'Europe'],
            ['code' => 'MX', 'name' => 'Mexico', 'continent' => 'North America'],
        ], $countries);

        self::assertIsString($sentBody);
        /** @var array{query: string, variables: array<string, mixed>} $payload */
        $payload = json_decode($sentBody, true, flags: \JSON_THROW_ON_ERROR);
        self::assertStringContainsString('countries', $payload['query']);
        self::assertSame([], $payload['variables']);
    }
}
