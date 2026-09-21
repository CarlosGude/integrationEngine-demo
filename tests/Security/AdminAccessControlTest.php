<?php

declare(strict_types=1);

namespace Tests\Security;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Guards T-03: /admin and the Mercure publish endpoints used to be reachable
 * anonymously, while inbound webhooks must stay that way — they authenticate
 * by HMAC signature, not by credentials.
 */
final class AdminAccessControlTest extends WebTestCase
{
    private const VALID_CREDENTIALS = [
        'PHP_AUTH_USER' => 'admin',
        'PHP_AUTH_PW' => 'demo-admin-change-me',
    ];

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function protectedEndpoints(): iterable
    {
        yield 'admin dashboard' => ['GET', '/admin'];
        yield 'mercure publish' => ['POST', '/api/mercure/publish'];
        yield 'mercure transactions' => ['POST', '/api/mercure/transactions'];
        yield 'mercure webhook shim' => ['POST', '/api/mercure/webhook'];
    }

    /**
     * @dataProvider protectedEndpoints
     */
    public function testProtectedEndpointRejectsAnonymousAccess(string $method, string $path): void
    {
        $client = self::createClient();
        $client->request($method, $path);

        self::assertResponseStatusCodeSame(401);
    }

    /**
     * @dataProvider protectedEndpoints
     */
    public function testProtectedEndpointRejectsWrongPassword(string $method, string $path): void
    {
        $client = self::createClient();
        $client->request($method, $path, [], [], [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'not-the-password',
        ]);

        self::assertResponseStatusCodeSame(401);
    }

    public function testAdminDashboardIsReachableWithValidCredentials(): void
    {
        $client = self::createClient();
        $client->request('GET', '/admin', [], [], self::VALID_CREDENTIALS);

        self::assertNotSame(401, $client->getResponse()->getStatusCode());
    }

    public function testInboundWebhookStaysAnonymous(): void
    {
        // Verified by HMAC signature, so credentials must not be demanded.
        // Any status but 401 proves the firewall let it through to the parser.
        $client = self::createClient();
        $client->request('POST', '/webhook/stripe');

        self::assertNotSame(401, $client->getResponse()->getStatusCode());
    }

    public function testPublicPagesStayAnonymous(): void
    {
        $client = self::createClient();
        $client->request('GET', '/en/tour/the-problem');

        self::assertResponseIsSuccessful();
    }
}
