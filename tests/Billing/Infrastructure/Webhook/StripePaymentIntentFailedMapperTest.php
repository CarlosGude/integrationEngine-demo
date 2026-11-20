<?php

declare(strict_types=1);

namespace Tests\Billing\Infrastructure\Webhook;

use App\Integrations\Stripe\Webhook\Mapper\StripePaymentIntentFailedMapper;
use App\Integrations\Stripe\Webhook\StripePaymentIntentEvent;
use PHPUnit\Framework\TestCase;

final class StripePaymentIntentFailedMapperTest extends TestCase
{
    public function testMapsThePaymentFailedPayloadInsteadOfReturningEmptyValues(): void
    {
        $payload = [
            'id' => 'evt_failed1',
            'type' => 'payment_intent.payment_failed',
            'data' => [
                'object' => [
                    'id' => 'pi_failed1',
                    'status' => 'requires_payment_method',
                    'amount' => 399,
                    'currency' => 'usd',
                    'metadata' => ['movie_id' => '550'],
                ],
            ],
        ];

        $event = StripePaymentIntentFailedMapper::map('payment_intent.payment_failed', $payload, []);
        \assert($event instanceof StripePaymentIntentEvent);

        self::assertSame('evt_failed1', $event->eventId);
        self::assertSame('payment_intent.payment_failed', $event->eventType);
        self::assertSame('pi_failed1', $event->paymentIntentId);
        self::assertSame(550, $event->movieId);
        self::assertSame('requires_payment_method', $event->status);
        self::assertSame(399, $event->amount);
        self::assertSame('usd', $event->currency);
    }
}
