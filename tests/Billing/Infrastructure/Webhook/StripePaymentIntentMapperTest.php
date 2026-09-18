<?php

declare(strict_types=1);

namespace Tests\Billing\Infrastructure\Webhook;

use App\Integrations\Stripe\Webhook\StripePaymentIntentEvent;
use App\Integrations\Stripe\Webhook\Mapper\StripePaymentIntentMapper;
use PHPUnit\Framework\TestCase;

final class StripePaymentIntentMapperTest extends TestCase
{
    private StripePaymentIntentMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new StripePaymentIntentMapper();
    }

    public function testMapStripePaymentIntentSucceededEvent(): void
    {
        $payload = [
            'id' => 'evt_test123',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test123',
                    'status' => 'succeeded',
                    'amount' => 500,
                    'currency' => 'usd',
                    'metadata' => ['movie_id' => 550],
                ],
            ],
        ];

        $event = $this->mapper->map($payload, []);

        self::assertInstanceOf(StripePaymentIntentEvent::class, $event);
        self::assertSame('evt_test123', $event->eventId);
        self::assertSame('payment_intent.succeeded', $event->eventType);
        self::assertSame('pi_test123', $event->paymentIntentId);
        self::assertSame(550, $event->movieId);
        self::assertSame('succeeded', $event->status);
        self::assertSame(500, $event->amount);
        self::assertSame('usd', $event->currency);
    }

    public function testGetDefinition(): void
    {
        self::assertSame('payment_intent.succeeded', $this->mapper->getDefinition());
    }

    public function testMapWithoutMetadata(): void
    {
        $payload = [
            'id' => 'evt_test456',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test456',
                    'status' => 'succeeded',
                    'amount' => 1000,
                    'currency' => 'eur',
                    'metadata' => [],
                ],
            ],
        ];

        $event = $this->mapper->map($payload, []);

        self::assertNull($event->movieId);
    }
}
