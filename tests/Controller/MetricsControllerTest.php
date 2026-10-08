<?php

declare(strict_types=1);

namespace Tests\Controller;

use PHPUnit\Framework\Attributes\Test;
use Prometheus\RenderTextFormat;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class MetricsControllerTest extends WebTestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['METRICS_ENABLED'], $_ENV['METRICS_ENABLED']);
        parent::tearDown();
    }

    #[Test]
    public function returnsNotFoundWhenMetricsAreDisabled(): void
    {
        $_SERVER['METRICS_ENABLED'] = $_ENV['METRICS_ENABLED'] = '0';

        $client = self::createClient();
        $client->request('GET', '/metrics');

        self::assertResponseStatusCodeSame(404);
    }

    #[Test]
    public function returnsPrometheusFormatWhenMetricsAreEnabled(): void
    {
        $_SERVER['METRICS_ENABLED'] = $_ENV['METRICS_ENABLED'] = '1';

        $client = self::createClient();
        $client->request('GET', '/metrics');

        self::assertResponseIsSuccessful();
        self::assertStringStartsWith(RenderTextFormat::MIME_TYPE, (string) $client->getResponse()->headers->get('Content-Type'));
    }
}
