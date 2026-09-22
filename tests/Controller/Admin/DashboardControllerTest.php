<?php

declare(strict_types=1);

namespace Tests\Controller\Admin;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DashboardControllerTest extends WebTestCase
{
    #[Test]
    public function adminDashboardRespondsWithAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin', server: [
            'PHP_AUTH_USER' => 'admin',
            'PHP_AUTH_PW' => 'demo-admin-change-me',
        ]);

        self::assertResponseStatusCodeSame(200);
    }

    #[Test]
    public function adminDashboardRequiresAuthentication(): void
    {
        $client = self::createClient();

        $client->request('GET', '/admin');

        self::assertResponseStatusCodeSame(401);
    }
}
