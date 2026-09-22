<?php

declare(strict_types=1);

namespace App\Integrations\Stripe\Webhook\Mapper;

use App\Integrations\Stripe\Webhook\StripePaymentIntentEvent;
use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

final class StripePaymentIntentFailedMapper extends AbstractWebhookMapper
{
    public static function eventType(): string
    {
        return 'payment_intent.payment_failed';
    }

    protected static function transform(array $payload, array $headers): WebhookEventInterface
    {
        return new StripePaymentIntentEvent('', 'payment_intent.payment_failed', '', null, '', 0, '');
    }
}
