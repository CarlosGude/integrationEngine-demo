<?php

declare(strict_types=1);

namespace Tests\Billing\Infrastructure\Webhook;

use App\Integrations\Stripe\Webhook\StripePaymentIntentEvent;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

final class StripeWebhookFlowTest extends WebTestCase
{
    // Matches STRIPE_WEBHOOK_SECRET in phpunit.xml.dist.
    private const SECRET = 'whsec_test';

    /** @var list<StripePaymentIntentEvent> */
    private array $dispatched = [];

    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();

        $dispatcher = self::getContainer()->get(EventDispatcherInterface::class);
        \assert($dispatcher instanceof EventDispatcherInterface);
        $dispatcher->addListener(
            StripePaymentIntentEvent::class,
            function (StripePaymentIntentEvent $event): void {
                $this->dispatched[] = $event;
            },
        );
    }

    public function testSignedPaymentIntentSucceededReachesListenersAsTypedEvent(): void
    {
        $body = $this->payload('payment_intent.succeeded');

        $this->post($body, $this->sign($body, time()));

        self::assertResponseStatusCodeSame(202);
        self::assertCount(1, $this->dispatched);
        self::assertSame('evt_test123', $this->dispatched[0]->eventId);
        self::assertSame('pi_test123', $this->dispatched[0]->paymentIntentId);
        self::assertSame(550, $this->dispatched[0]->movieId);
        self::assertSame(500, $this->dispatched[0]->amount);
    }

    public function testInvalidSignatureIsRejected(): void
    {
        $body = $this->payload('payment_intent.succeeded');

        $this->post($body, 't='.time().',v1=forged');

        self::assertResponseStatusCodeSame(406);
        self::assertSame([], $this->dispatched);
    }

    public function testExpiredSignatureIsRejected(): void
    {
        $body = $this->payload('payment_intent.succeeded');

        $this->post($body, $this->sign($body, time() - 3600));

        self::assertResponseStatusCodeSame(406);
        self::assertSame([], $this->dispatched);
    }

    public function testOtherEventTypesAreAcknowledgedButNotDispatched(): void
    {
        $body = $this->payload('payment_intent.payment_failed');

        $this->post($body, $this->sign($body, time()));

        self::assertResponseStatusCodeSame(202);
        self::assertSame([], $this->dispatched);
    }

    private function payload(string $type): string
    {
        return json_encode([
            'id' => 'evt_test123',
            'type' => $type,
            'data' => [
                'object' => [
                    'id' => 'pi_test123',
                    'status' => 'succeeded',
                    'amount' => 500,
                    'currency' => 'usd',
                    'metadata' => ['movie_id' => '550'],
                ],
            ],
        ], \JSON_THROW_ON_ERROR);
    }

    private function sign(string $body, int $timestamp): string
    {
        return \sprintf('t=%d,v1=%s', $timestamp, hash_hmac('sha256', "{$timestamp}.{$body}", self::SECRET));
    }

    private function post(string $body, string $signature): void
    {
        $this->client->request(
            'POST',
            '/webhook/stripe',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_STRIPE_SIGNATURE' => $signature,
            ],
            content: $body,
        );
    }
}
