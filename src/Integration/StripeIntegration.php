<?php

declare(strict_types=1);

namespace App\Integration;

use App\Integrations\Stripe\CreatePaymentIntent\CreatePaymentIntentRequest;
use App\Integrations\Stripe\CreatePaymentIntent\CreatePaymentIntentResponse;
use IntegrationEngine\Core\Registry\IntegrationRegistry;

final readonly class StripeIntegration
{
    public function __construct(
        private IntegrationRegistry $registry,
    ) {
    }

    public function createPaymentIntent(int $amountCents, string $currency = 'usd', array $metadata = []): CreatePaymentIntentResponse
    {
        $engine = $this->registry->get('stripe');
        $body = CreatePaymentIntentRequest::create([
            'amount' => $amountCents,
            'currency' => $currency,
            'metadata' => $metadata,
        ]);
        $response = $engine->send('create_payment_intent', body: $body);

        return $response;
    }
}
