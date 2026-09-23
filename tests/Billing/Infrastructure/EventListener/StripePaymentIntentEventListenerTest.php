<?php

declare(strict_types=1);

namespace Tests\Billing\Infrastructure\EventListener;

use App\Billing\Infrastructure\EventListener\StripePaymentIntentEventListener;
use App\Integrations\Stripe\Webhook\StripePaymentIntentEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;

final class StripePaymentIntentEventListenerTest extends TestCase
{
    #[Test]
    public function logsTheFullEventContextAtInfoLevel(): void
    {
        $event = new StripePaymentIntentEvent(
            eventId: 'evt_123',
            eventType: 'payment_intent.succeeded',
            paymentIntentId: 'pi_123',
            movieId: 550,
            status: 'succeeded',
            amount: 500,
            currency: 'usd',
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(
                'Stripe payment intent succeeded',
                [
                    'event_id' => 'evt_123',
                    'event_type' => 'payment_intent.succeeded',
                    'payment_intent_id' => 'pi_123',
                    'movie_id' => 550,
                    'status' => 'succeeded',
                    'amount' => 500,
                    'currency' => 'usd',
                ],
            );

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())->method('publish')->willReturn('urn:uuid:test');
        $listener = new StripePaymentIntentEventListener($logger, $hub);
        $listener->onStripePaymentIntentSucceeded($event);
    }

    #[Test]
    public function logsANullMovieIdWhenTheEventHasNone(): void
    {
        $event = new StripePaymentIntentEvent(
            eventId: 'evt_456',
            eventType: 'payment_intent.succeeded',
            paymentIntentId: 'pi_456',
            movieId: null,
            status: 'succeeded',
            amount: 1000,
            currency: 'eur',
        );

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('info')
            ->with(self::anything(), self::callback(
                static fn (array $context): bool => \array_key_exists('movie_id', $context) && $context['movie_id'] === null,
            ));

        $hub = $this->createMock(HubInterface::class);
        $hub->expects(self::once())->method('publish')->willReturn('urn:uuid:test');
        $listener = new StripePaymentIntentEventListener($logger, $hub);
        $listener->onStripePaymentIntentSucceeded($event);
    }
}
