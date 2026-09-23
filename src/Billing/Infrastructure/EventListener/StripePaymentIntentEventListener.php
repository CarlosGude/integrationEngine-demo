<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\EventListener;

use App\Integrations\Stripe\Webhook\StripePaymentIntentEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

final readonly class StripePaymentIntentEventListener
{
    public function __construct(
        private LoggerInterface $logger,
        private HubInterface $hub,
    ) {
    }

    #[AsEventListener(event: StripePaymentIntentEvent::class)]
    public function onStripePaymentIntentSucceeded(StripePaymentIntentEvent $event): void
    {
        $succeeded = $event->eventType === 'payment_intent.succeeded';
        $action = $succeeded ? 'payment_succeeded' : 'payment_failed';

        $this->logger->info($succeeded ? 'Stripe payment intent succeeded' : 'Stripe payment intent failed', [
            'event_id' => $event->eventId,
            'event_type' => $event->eventType,
            'payment_intent_id' => $event->paymentIntentId,
            'movie_id' => $event->movieId,
            'status' => $event->status,
            'amount' => $event->amount,
            'currency' => $event->currency,
        ]);

        $payload = json_encode([
            'topic' => 'admin/payments',
            'action' => $action,
            'eventId' => $event->eventId,
            'paymentIntentId' => $event->paymentIntentId,
            'movieId' => $event->movieId,
            'status' => $event->status,
            'amount' => $event->amount,
            'currency' => $event->currency,
            'timestamp' => date('c'),
        ], JSON_THROW_ON_ERROR);

        $this->hub->publish(new Update('admin/payments', $payload));
    }
}
