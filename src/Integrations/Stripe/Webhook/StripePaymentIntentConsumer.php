<?php

declare(strict_types=1);

namespace App\Integrations\Stripe\Webhook;

use App\Integrations\Stripe\Webhook\Mapper\StripePaymentIntentMapper;
use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Infrastructure\Webhook\ConsumesWebhookEvents;
use Symfony\Component\RemoteEvent\Attribute\AsRemoteEventConsumer;
use Symfony\Component\RemoteEvent\Consumer\ConsumerInterface;

// tour:start webhook/consumer
/**
 * Turns the verified RemoteEvent into a typed StripePaymentIntentEvent and
 * dispatches it — listeners (e.g. Billing) never see the raw payload.
 */
#[AsRemoteEventConsumer('stripe')]
final class StripePaymentIntentConsumer implements ConsumerInterface
{
    use ConsumesWebhookEvents;

    protected function mapper(): AbstractWebhookMapper
    {
        return new StripePaymentIntentMapper();
    }
}
// tour:end
