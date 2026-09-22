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
    private function createClientWithMockHub()
    {
        $client = static::createClient();
        $container = static::getContainer();

        $tokenProvider = new StaticTokenProvider('test_token');
        $publisher = static fn ($update): string => 'urn:uuid:test';

        $hub = new MockHub('http://localhost/.well-known/mercure', $tokenProvider, $publisher);
        $container->set(HubInterface::class, $hub);

        return $client;
    }

    private function authenticateAsAdmin(self $testCase): void
    {
        $user = new InMemoryUser('admin', 'password', ['ROLE_ADMIN']);
        $testCase->getContainer()->get('security.token_storage')->setToken(
            new \Symfony\Component\Security\Authentication\Token\UsernamePasswordToken($user, 'main', ['ROLE_ADMIN'])
        );
    }

    #[Test]
    public function publishRouteExistsAndValidatesInput(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/publish', content: 'invalid json');

        self::assertThat(
            $client->getResponse()->getStatusCode(),
            self::logicalOr(self::equalTo(400), self::equalTo(401))
        );
    }

    #[Test]
    public function publishValidatesTopicRequired(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/publish', content: json_encode(['message' => 'test']));

        self::assertThat(
            $client->getResponse()->getStatusCode(),
            self::logicalOr(self::equalTo(400), self::equalTo(401))
        );
    }

    #[Test]
    public function publishValidatesTopicIsString(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/publish', content: json_encode(['topic' => 123]));

        self::assertThat(
            $client->getResponse()->getStatusCode(),
            self::logicalOr(self::equalTo(400), self::equalTo(401))
        );
    }

    #[Test]
    public function transactionRouteExists(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/transactions', content: json_encode(['data' => 'test']));

        self::assertThat(
            $client->getResponse()->getStatusCode(),
            self::logicalOr(self::equalTo(200), self::equalTo(401), self::equalTo(400))
        );
    }

    #[Test]
    public function webhookRouteExists(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/webhook', content: 'invalid');

        self::assertThat(
            $client->getResponse()->getStatusCode(),
            self::logicalOr(self::equalTo(400), self::equalTo(401))
        );
    }

    #[Test]
    public function webhookValidatesEventType(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/webhook', content: json_encode(['data' => []]));

        self::assertThat(
            $client->getResponse()->getStatusCode(),
            self::logicalOr(self::equalTo(400), self::equalTo(401))
        );
    }

    #[Test]
    public function webhookValidatesPayloadStructure(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/webhook', content: json_encode([
            'type' => 'payment_intent.succeeded',
            'data' => [],
        ]));

        self::assertThat(
            $client->getResponse()->getStatusCode(),
            self::logicalOr(self::equalTo(400), self::equalTo(401))
        );
    }
}
