<?php

declare(strict_types=1);

namespace App\Integrations\Stripe\Webhook;

use IntegrationEngine\Core\Contract\Webhook\WebhookEventInterface;

final readonly class StripePaymentIntentEvent implements WebhookEventInterface
{
    public function __construct(
        public string $eventId,
        public string $eventType,
        public string $paymentIntentId,
        public ?int $movieId,
        public string $status,
        public int $amount,
        public string $currency,
    ) {
    }
}
