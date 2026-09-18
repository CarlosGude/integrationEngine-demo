<?php

declare(strict_types=1);

namespace App\Integrations\Stripe\Webhook;

use App\Integrations\Stripe\Webhook\Mapper\StripePaymentIntentMapper;
use IntegrationEngine\Infrastructure\Webhook\WebhookEventDispatcher;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;

// tour:start webhook/consumer
/**
 * Turns the verified RemoteEvent into a typed StripePaymentIntentEvent and
 * dispatches it — listeners (e.g. Billing) never see the raw payload.
 */
#[AsRemoteEventConsumer('stripe')]
final readonly class StripePaymentIntentConsumer implements ConsumerInterface
{
    public function __construct(
        private WebhookEventDispatcher $dispatcher,
    ) {
    }

    public function consume(RemoteEvent $event): void
    {
        $mapper = new StripePaymentIntentMapper();

        // The parser names every RemoteEvent after its definition, so the
        // payload's own type is what tells a succeeded intent apart from any
        // other event the Stripe endpoint sends. Others are acknowledged (202)
        // and ignored, so Stripe doesn't retry them.
        if (($event->getPayload()['type'] ?? null) !== $mapper->getDefinition()) {
            return;
        }

        $this->dispatcher->dispatch($event, $mapper, []);
    }
}
// tour:end
