<?php

declare(strict_types=1);

namespace App\Billing\Application;

use App\Integrations\Stripe\Webhook\StripePaymentIntentEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class PaymentConfirmationSimulator
{
    public function __construct(
        private EventDispatcherInterface $events,
    ) {
    }

    /** @return array<string, int|string|null> */
    public function simulate(int $movieId, int $amount, string $currency): array
    {
        $event = new StripePaymentIntentEvent(
            eventId: 'evt_demo',
            eventType: 'payment_intent.succeeded',
            paymentIntentId: 'pi_demo',
            movieId: $movieId,
            status: 'succeeded',
            amount: $amount,
            currency: $currency,
        );

        $this->events->dispatch($event);

        return [
            'event' => $event->eventType,
            'payment_intent_id' => $event->paymentIntentId,
            'mercure_topic' => 'admin/payments',
            'mode' => 'typed-event downstream simulation',
        ];
    }
}
