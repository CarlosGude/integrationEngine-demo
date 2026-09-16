<?php

declare(strict_types=1);

namespace Tests\Tour\UI;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

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
        $client->request('POST', '/en/tour/the-problem/run', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        /** @var array<string, mixed>|null */
        $data = json_decode($content, true);
        self::assertIsArray($data);
        self::assertArrayHasKey('result', $data);
        self::assertArrayHasKey('trace', $data);
        $trace = $data['trace'];
        self::assertIsArray($trace);
        self::assertArrayHasKey('calls', $trace);
    }
}
