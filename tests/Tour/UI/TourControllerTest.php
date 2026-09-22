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
        $client->request('POST', '/en/tour/the-problem/run', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], $payload);

        self::assertResponseIsSuccessful();
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        /** @var array<string, mixed>|null */
        $data = json_decode($content, true);
        self::assertIsArray($data);

        self::assertSame(
            ['result' => ['success' => true, 'message' => 'Step executed']],
            ['result' => $data['result']],
        );

        $trace = $data['trace'];
        self::assertIsArray($trace);
        // json_decode() gives back an int 0, not a float 0.0, for a whole
        // number without JSON_PRESERVE_ZERO_FRACTION on the encoding side.
        self::assertSame(['calls' => [], 'total_duration_ms' => 0], $trace);
    }

    public function testRunEndpointFor404sForAnUnknownStepWithoutRunningIt(): void
    {
        $client = self::createClient();
        $client->request('POST', '/en/tour/nonexistent/run');

        self::assertResponseStatusCodeSame(404);
    }
}
