<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Webhook;

use Psr\Log\LoggerInterface;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

// tour:start webhook/consumer
#[AsRemoteEventConsumer('stripe')]
final readonly class StripePaymentIntentConsumer implements ConsumerInterface
{
    public function __construct(
        private LoggerInterface $logger,
    ) {
    }

    public function consume(RemoteEvent $event): void
    {
        $payload = $event->getPayload();

        $this->logger->info('Stripe webhook received', [
            'event_type' => $event->getName(),
            'event_id' => $event->getId(),
            'payload' => $payload,
        ]);

        if (isset($payload['data']['object'])) {
            $obj = $payload['data']['object'];
            $movieId = $obj['metadata']['movie_id'] ?? null;
            $paymentIntentId = $obj['id'] ?? null;
            $status = $obj['status'] ?? null;

            $this->logger->info('Stripe payment intent event', [
                'payment_intent_id' => $paymentIntentId,
                'movie_id' => $movieId,
                'status' => $status,
            ]);
        }
    }
}
// tour:end
