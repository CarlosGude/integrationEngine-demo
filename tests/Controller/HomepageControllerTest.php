<?php

declare(strict_types=1);

namespace Tests\Controller;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class HomepageControllerTest extends WebTestCase
{
    #[Test]
    public function rootRedirectsToTheEnglishLocalizedHomepage(): void
    {
        $client = self::createClient();
        $client->request('GET', '/');

        self::assertResponseRedirects('/en/');
    }

    #[Test]
    public function englishLocalizedHomepageRenders(): void
    {
        $client = self::createClient();
        $client->request('GET', '/en/');

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function spanishLocalizedHomepageRenders(): void
    {
        $client = self::createClient();
        $client->request('GET', '/es/');

        self::assertResponseIsSuccessful();
    }

    #[Test]
    public function unsupportedLocaleIsRejected(): void
    {
        $client = self::createClient();
        $client->request('GET', '/fr/');

        self::assertResponseStatusCodeSame(404);
    }
}
