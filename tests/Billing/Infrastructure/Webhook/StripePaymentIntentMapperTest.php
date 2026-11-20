<?php

declare(strict_types=1);

namespace Tests\Billing\Infrastructure\Webhook;

use App\Integrations\Stripe\Webhook\Mapper\StripePaymentIntentMapper;
use App\Integrations\Stripe\Webhook\StripePaymentIntentEvent;
use App\Shared\Infrastructure\Integration\UnexpectedApiResponse;
use PHPUnit\Framework\TestCase;

final class StripePaymentIntentMapperTest extends TestCase
{
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

        $event = StripePaymentIntentMapper::map('payment_intent.succeeded', $payload, []);

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

        $event = StripePaymentIntentMapper::map('payment_intent.succeeded', $payload, []);

        self::assertInstanceOf(StripePaymentIntentEvent::class, $event);
        self::assertNull($event->movieId);
    }

    public function testMapReadsTheStringMovieIdStripeSendsInMetadata(): void
    {
        $event = StripePaymentIntentMapper::map('payment_intent.succeeded', self::payload(['metadata' => ['movie_id' => '550']]), []);
        \assert($event instanceof StripePaymentIntentEvent);

        self::assertSame(550, $event->movieId);
    }

    public function testRejectsAnAmountThatIsNotAnInteger(): void
    {
        $this->expectException(UnexpectedApiResponse::class);
        $this->expectExceptionMessage('Stripe webhook data.object: field "amount" is missing or not an integer.');

        StripePaymentIntentMapper::map('payment_intent.succeeded', self::payload(['amount' => '750']), []);
    }

    public function testRejectsANonNumericMovieId(): void
    {
        $this->expectException(UnexpectedApiResponse::class);
        $this->expectExceptionMessage('field "metadata.movie_id" is missing or not a numeric movie id');

        StripePaymentIntentMapper::map('payment_intent.succeeded', self::payload(['metadata' => ['movie_id' => 'abc']]), []);
    }

    public function testRejectsAPayloadWithoutItsEventId(): void
    {
        $payload = self::payload();
        unset($payload['id']);

        $this->expectException(UnexpectedApiResponse::class);
        $this->expectExceptionMessage('Stripe webhook: field "id" is missing or not a string.');

        StripePaymentIntentMapper::map('payment_intent.succeeded', $payload, []);
    }

    public function testRejectsAnEmptyPayload(): void
    {
        $this->expectException(UnexpectedApiResponse::class);

        StripePaymentIntentMapper::map('payment_intent.succeeded', [], []);
    }

    /**
     * @param array<string, mixed> $object overrides for data.object
     *
     * @return array<string, mixed>
     */
    private static function payload(array $object = []): array
    {
        return [
            'id' => 'evt_test789',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => $object + [
                    'id' => 'pi_test789',
                    'status' => 'succeeded',
                    'amount' => 750,
                    'currency' => 'usd',
                ],
            ],
        ];
    }
}
