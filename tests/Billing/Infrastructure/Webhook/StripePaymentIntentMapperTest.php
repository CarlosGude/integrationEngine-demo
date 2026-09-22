<?php

declare(strict_types=1);

namespace Tests\Billing\Infrastructure\Webhook;

use App\Integrations\Stripe\Webhook\Mapper\StripePaymentIntentMapper;
use App\Integrations\Stripe\Webhook\StripePaymentIntentEvent;
use PHPUnit\Framework\TestCase;

final class StripePaymentIntentMapperTest extends TestCase
{
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

        $event = StripePaymentIntentMapper::map($payload, []);

        self::assertInstanceOf(StripePaymentIntentEvent::class, $event);
        self::assertSame('evt_test123', $event->eventId);
        self::assertSame('payment_intent.succeeded', $event->eventType);
        self::assertSame('pi_test123', $event->paymentIntentId);
        self::assertSame(550, $event->movieId);
        self::assertSame('succeeded', $event->status);
        self::assertSame(500, $event->amount);
        self::assertSame('usd', $event->currency);
    }

    public function testEventType(): void
    {
        self::assertSame('payment_intent.succeeded', StripePaymentIntentMapper::eventType());
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

        $event = StripePaymentIntentMapper::map($payload, []);

        self::assertInstanceOf(StripePaymentIntentEvent::class, $event);
        self::assertNull($event->movieId);
    }

    public function testMapCastsNonStringAndNonIntFieldsToTheirDeclaredTypes(): void
    {
        $payload = [
            'id' => 12345,
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 999,
                    'status' => 'succeeded',
                    'amount' => '750',
                    'currency' => 'usd',
                    'metadata' => ['movie_id' => '550'],
                ],
            ],
        ];

        $event = StripePaymentIntentMapper::map($payload, []);
        \assert($event instanceof StripePaymentIntentEvent);

        self::assertSame('12345', $event->eventId);
        self::assertIsString($event->eventId);
        self::assertSame('999', $event->paymentIntentId);
        self::assertIsString($event->paymentIntentId);
        self::assertSame(550, $event->movieId);
        self::assertIsInt($event->movieId);
        self::assertSame(750, $event->amount);
        self::assertIsInt($event->amount);
    }

    public function testMapDefaultsMissingTopLevelFieldsToEmptyValues(): void
    {
        $event = StripePaymentIntentMapper::map([], []);
        \assert($event instanceof StripePaymentIntentEvent);

        self::assertSame('', $event->eventId);
        self::assertSame('', $event->eventType);
        self::assertSame('', $event->paymentIntentId);
        self::assertNull($event->movieId);
        self::assertSame('', $event->status);
        self::assertSame(0, $event->amount);
        self::assertSame('', $event->currency);
    }
}
