<?php

declare(strict_types=1);

namespace Tests\Shared\UI;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;

final class MercureUpdateControllerTest extends WebTestCase
{
    /** @var list<Update> */
    private array $publishedUpdates = [];

    protected function setUp(): void
    {
        $this->publishedUpdates = [];
    }

    private function createClientWithMockHub(): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = self::createClient();
        $container = self::getContainer();

        $tokenProvider = new StaticTokenProvider('test_token');
        $publisher = function (Update $update): string {
            $this->publishedUpdates[] = $update;

            return 'urn:uuid:test';
        };

        $hub = new MockHub('http://localhost/.well-known/mercure', $tokenProvider, $publisher);
        $container->set(HubInterface::class, $hub);

        return $client;
    }

    /** @return array<array-key, mixed> */
    private function decodeJsonBody(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client): array
    {
        $content = $client->getResponse()->getContent();
        self::assertIsString($content);
        $data = json_decode($content, true, flags: \JSON_THROW_ON_ERROR);
        self::assertIsArray($data);

        return $data;
    }

    // --- publish() ---

    #[Test]
    public function publishRejectsInvalidJson(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/publish', content: 'invalid json');

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'Invalid JSON'], $this->decodeJsonBody($client));
        self::assertSame([], $this->publishedUpdates);
    }

    #[Test]
    public function publishRequiresATopic(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/publish', content: json_encode(['message' => 'test'], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'Topic is required and must be a string'], $this->decodeJsonBody($client));
        self::assertSame([], $this->publishedUpdates);
    }

    #[Test]
    public function publishRejectsANonStringTopic(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/publish', content: json_encode(['topic' => 123], \JSON_THROW_ON_ERROR));

        self::assertResponseStatusCodeSame(400);
        self::assertSame(['error' => 'Topic is required and must be a string'], $this->decodeJsonBody($client));
        self::assertSame([], $this->publishedUpdates);
    }

    #[Test]
    public function publishSucceedsAndMergesTheMessageIntoThePayload(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/publish', content: json_encode([
            'topic' => 'movies/550',
            'message' => ['title' => 'Fight Club', 'available' => true],
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        self::assertSame(['success' => true, 'topic' => 'movies/550'], $this->decodeJsonBody($client));

        self::assertCount(1, $this->publishedUpdates);
        $update = $this->publishedUpdates[0];
        self::assertSame(['movies/550'], $update->getTopics());

        /** @var array<string, mixed> $data */
        $data = json_decode($update->getData(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('Fight Club', $data['title']);
        self::assertTrue($data['available']);
        self::assertArrayHasKey('timestamp', $data);
    }

    #[Test]
    public function publishWithoutAMessageStillPublishesJustATimestamp(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/publish', content: json_encode(['topic' => 'movies/550'], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->publishedUpdates);

        /** @var array<string, mixed> $data */
        $data = json_decode($this->publishedUpdates[0]->getData(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['timestamp'], array_keys($data));
    }

    #[Test]
    public function publishWithANonArrayMessageIgnoresIt(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/publish', content: json_encode([
            'topic' => 'movies/550',
            'message' => 'not-an-array',
        ], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        /** @var array<string, mixed> $data */
        $data = json_decode($this->publishedUpdates[0]->getData(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame(['timestamp'], array_keys($data));
    }

    // --- publishTransaction() ---

    #[Test]
    public function publishTransactionAlwaysPublishesToTheAdminTransactionsTopic(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/transactions', content: json_encode(['amount' => 500], \JSON_THROW_ON_ERROR));

        self::assertResponseIsSuccessful();
        self::assertSame(
            ['success' => true, 'message' => 'Transaction published'],
            $this->decodeJsonBody($client),
        );

        self::assertCount(1, $this->publishedUpdates);
        $update = $this->publishedUpdates[0];
        self::assertSame(['admin/transactions'], $update->getTopics());

        /** @var array<string, mixed> $data */
        $data = json_decode($update->getData(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame('new_transaction', $data['action']);
        self::assertSame(['amount' => 500], $data['transaction']);
        self::assertArrayHasKey('timestamp', $data);
    }

    #[Test]
    public function publishTransactionTreatsInvalidJsonAsAnEmptyTransaction(): void
    {
        $client = $this->createClientWithMockHub();

        $client->request('POST', '/api/mercure/transactions', content: 'not json at all');

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->publishedUpdates);

        /** @var array<string, mixed> $data */
        $data = json_decode($this->publishedUpdates[0]->getData(), true, flags: \JSON_THROW_ON_ERROR);
        self::assertSame([], $data['transaction']);
    }

}
