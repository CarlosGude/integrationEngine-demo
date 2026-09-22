<?php

declare(strict_types=1);

namespace App\Integrations\Stripe\Webhook;

use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

// tour:start webhook/consumer
/**
 * In v8.0, webhook events are handled automatically by the IntegrationEngine bundle
 * via the YAML configuration in Stripe.yaml. The engine creates typed events
 * (StripePaymentIntentEvent) that you can listen to with an EventListener.
 *
 * Example listener:
 * #[AsEventListener]
 * public function onStripePaymentIntentSucceeded(StripePaymentIntentEvent $event): void
 * {
 *     // Handle the webhook event
 * }
 */
#[AsRemoteEventConsumer('stripe')]
final readonly class StripePaymentIntentConsumer implements ConsumerInterface
{
    public function consume(RemoteEvent $event): void
    {
        // The event has already been verified and mapped by the engine
        // Dispatch it to event listeners
    }
}
// tour:end
