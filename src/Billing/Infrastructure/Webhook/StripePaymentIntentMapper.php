<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Webhook;

use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

// tour:start webhook/mapper
final class StripePaymentIntentMapper extends AbstractWebhookMapper
{
    public function getDefinition(): string
    {
        return 'payment_intent.succeeded';
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    public function map(array $payload, array $headers): WebhookEventInterface
    {
        $obj = $payload['data']['object'] ?? [];

        return new StripePaymentIntentEvent(
            eventId: (string) ($payload['id'] ?? ''),
            eventType: (string) ($payload['type'] ?? ''),
            paymentIntentId: (string) ($obj['id'] ?? ''),
            movieId: isset($obj['metadata']['movie_id']) ? (int) $obj['metadata']['movie_id'] : null,
            status: (string) ($obj['status'] ?? ''),
            amount: (int) ($obj['amount'] ?? 0),
            currency: (string) ($obj['currency'] ?? ''),
        );
    }
}
// tour:end
