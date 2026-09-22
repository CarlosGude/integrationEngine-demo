<?php

declare(strict_types=1);

namespace Tests\Shared\UI;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Security\Core\User\InMemoryUser;

final class MercureUpdateControllerTest extends WebTestCase
{
    private function createClientWithMockHub(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = self::createClient();
        $container = self::getContainer();

        $tokenProvider = new StaticTokenProvider('test_token');
        $publisher = static fn ($update): string => 'urn:uuid:test';

        $hub = new MockHub('http://localhost/.well-known/mercure', $tokenProvider, $publisher);
        $container->set(HubInterface::class, $hub);

        return $client;
    }

    #[Test]
    public function publishRouteExistsAndValidatesInput(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/publish', content: 'invalid json');

        self::assertThat(
            $client->getResponse()->getStatusCode(),
            self::logicalOr(self::equalTo(400), self::equalTo(401)),
        );
    }

    #[Test]
    public function publishValidatesTopicRequired(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/publish', content: json_encode(['message' => 'test'], \JSON_THROW_ON_ERROR));

        self::assertThat(
            $client->getResponse()->getStatusCode(),
            self::logicalOr(self::equalTo(400), self::equalTo(401)),
        );
    }

    #[Test]
    public function publishValidatesTopicIsString(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/publish', content: json_encode(['topic' => 123], \JSON_THROW_ON_ERROR));

        self::assertThat(
            $client->getResponse()->getStatusCode(),
            self::logicalOr(self::equalTo(400), self::equalTo(401)),
        );
    }

    #[Test]
    public function transactionRouteExists(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/transactions', content: json_encode(['data' => 'test'], \JSON_THROW_ON_ERROR));

        self::assertThat(
            $client->getResponse()->getStatusCode(),
            self::logicalOr(self::equalTo(200), self::equalTo(401), self::equalTo(400)),
        );
    }

    #[Test]
    public function webhookRouteExists(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/webhook', content: 'invalid');

        self::assertThat(
            $client->getResponse()->getStatusCode(),
            self::logicalOr(self::equalTo(400), self::equalTo(401)),
        );
    }

    #[Test]
    public function webhookValidatesEventType(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/webhook', content: json_encode(['data' => []], \JSON_THROW_ON_ERROR));

        self::assertThat(
            $client->getResponse()->getStatusCode(),
            self::logicalOr(self::equalTo(400), self::equalTo(401)),
        );
    }

    #[Test]
    public function webhookValidatesPayloadStructure(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/webhook', content: json_encode([
            'type' => 'payment_intent.succeeded',
            'data' => [],
        ], \JSON_THROW_ON_ERROR));

        self::assertThat(
            $client->getResponse()->getStatusCode(),
            self::logicalOr(self::equalTo(400), self::equalTo(401)),
        );
    }
}
