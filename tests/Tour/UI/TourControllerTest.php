<?php

declare(strict_types=1);

namespace Tests\Tour\UI;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class TourControllerTest extends WebTestCase
{
    public function testStepPageLoads(): void
    {
        $client = self::createClient();
        $client->request('GET', '/en/tour/the-problem');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('The Problem', $content);
    }

    public function testFirstStepHasNoPreviousStepButHasANextStep(): void
    {
        $client = self::createClient();
        $client->request('GET', '/en/tour/the-problem');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        // The CSS rule for .btn-prev is always present in the page's
        // <style> block, so check for the rendered element, not the bare
        // class name.
        self::assertStringNotContainsString('class="btn-prev"', $content);
        self::assertStringContainsString('href="/en/tour/parallel-requests" class="btn-next"', $content);
    }

    public function testMiddleStepHasBothPreviousAndNextSteps(): void
    {
        $client = self::createClient();
        $client->request('GET', '/en/tour/parallel-requests');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringContainsString('href="/en/tour/the-problem" class="btn-prev"', $content);
        self::assertStringContainsString('href="/en/tour/behind-the-counter" class="btn-next"', $content);
    }

    public function testLastStepHasAPreviousStepButNoNextStep(): void
    {
        $client = self::createClient();
        $client->request('GET', '/en/tour/payment-confirmation');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        self::assertStringNotContainsString('class="btn-next"', $content);
        self::assertStringContainsString('class="btn-prev"', $content);
    }

    public function testStepNotFoundReturns404(): void
    {
        $client = self::createClient();
        $client->request('GET', '/en/tour/nonexistent');

        self::assertResponseStatusCodeSame(404);
    }

    public function testRunEndpointReturnsJson(): void
    {
        $client = self::createClient();
        $payload = json_encode(['action' => 'test']);
        self::assertIsString($payload);
        $client->request('POST', '/en/tour/graceful-degradation/run', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        /** @var array<string, mixed>|null */
        $data = json_decode($content, true);
        self::assertIsArray($data);

        $result = $data['result'] ?? null;
        self::assertIsArray($result);
        self::assertTrue($result['success']);
        self::assertSame(
            ['null' => null, 'cache' => ['price' => '2.99', 'currency' => 'USD'], 'default' => ['stock' => 0]],
            $result['data'],
        );

        $trace = $data['trace'];
        self::assertIsArray($trace);
        self::assertSame([], $trace['calls']);
    }

    public function testRunTraceComesFromTheEngineLifecycleEvents(): void
    {
        $client = self::createClient();
        self::getContainer()->set('http_client', new MockHttpClient([
            new MockResponse((string) json_encode([
                'id' => 550,
                'title' => 'Fight Club',
                'overview' => 'Overview',
                'poster_path' => '/poster.jpg',
                'vote_average' => 8.4,
                'release_date' => '1999-10-15',
            ]), ['http_code' => 200]),
            new MockResponse((string) json_encode([
                'images' => ['secure_base_url' => 'https://image.tmdb.org/t/p/', 'poster_sizes' => ['w500']],
            ]), ['http_code' => 200]),
        ]));

        $client->request('POST', '/en/tour/the-problem/run');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        /** @var array{result: array{success: bool}, trace: array{calls: list<array<string, mixed>>, total_duration_ms: float}} $data */
        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        self::assertTrue($data['result']['success']);
        $calls = $data['trace']['calls'];
        self::assertSame(['tmdb.get_movie', 'tmdb.get_configuration'], array_column($calls, 'action'));
        foreach ($calls as $call) {
            self::assertSame('GET', $call['method']);
            self::assertSame(200, $call['status']);
            self::assertSame('mapped', $call['outcome']);
            self::assertIsFloat($call['upstream_ms']);
            self::assertIsFloat($call['mapping_ms']);
        }
    }

    public function testRunTraceShowsTheRealStatusOfAFailedCall(): void
    {
        $client = self::createClient();
        self::getContainer()->set('http_client', new MockHttpClient([
            new MockResponse('{"status_message":"Invalid API key"}', ['http_code' => 401]),
        ]));

        $client->request('POST', '/en/tour/the-problem/run');

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        /** @var array{result: array{success: bool}, trace: array{calls: list<array<string, mixed>>}} $data */
        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);

        self::assertFalse($data['result']['success']);
        self::assertCount(1, $data['trace']['calls']);
        self::assertSame('tmdb.get_movie', $data['trace']['calls'][0]['action']);
        self::assertSame(401, $data['trace']['calls'][0]['status']);
        self::assertSame('failed', $data['trace']['calls'][0]['outcome']);
    }

    public function testRunEndpointFor404sForAnUnknownStepWithoutRunningIt(): void
    {
        $client = self::createClient();
        $client->request('POST', '/en/tour/nonexistent/run');

        self::assertResponseStatusCodeSame(404);
    }
}
