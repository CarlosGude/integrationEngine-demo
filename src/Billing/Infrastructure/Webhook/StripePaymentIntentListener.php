<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Webhook;

use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

final readonly class StripePaymentIntentListener
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    #[AsEventListener(event: StripePaymentIntentEvent::class)]
    public function onStripePaymentIntentSucceeded(StripePaymentIntentEvent $event): void
    {
        $this->logger->info('Stripe payment intent succeeded', [
            'event_id' => $event->eventId,
            'event_type' => $event->eventType,
            'payment_intent_id' => $event->paymentIntentId,
            'movie_id' => $event->movieId,
            'status' => $event->status,
            'amount' => $event->amount,
            'currency' => $event->currency,
        ]);
    }
}
