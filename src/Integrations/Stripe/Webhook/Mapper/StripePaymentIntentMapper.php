<?php

declare(strict_types=1);

namespace App\Integrations\Stripe\Webhook\Mapper;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

final class StripePaymentIntentMapper extends AbstractWebhookMapper
{
    public static function eventType(): string
    {
        return 'payment_intent.succeeded';
    }

    /**
     * @param array<string, mixed>  $payload
     * @param array<string, string> $headers
     */
    protected static function transform(array $payload, array $headers): WebhookEventInterface
    {
        return PaymentIntentEventPayload::toEvent($payload);
    }
}
