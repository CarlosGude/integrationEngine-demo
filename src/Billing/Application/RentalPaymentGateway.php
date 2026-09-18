<?php

declare(strict_types=1);

namespace App\Billing\Application;

use App\Billing\Domain\RentalPayment;
use App\Billing\Infrastructure\Integrations\Stripe\CreatePaymentIntentBody;
use App\Billing\Infrastructure\Integrations\Stripe\CreatePaymentIntentResponse;
use IntegrationEngine\Core\Contract\Action\DefaultActionContext;
use IntegrationEngine\Core\Registry\IntegrationRegistry;

final class RentalPaymentGateway
{
    public function __construct(
        private readonly IntegrationRegistry $integrationRegistry,
    ) {
    }

    // tour:start solution/payment-integration
    public function rentMovie(
        int $movieId,
        int $amountCents = 500,
        string $currency = 'usd',
    ): RentalPayment {
        $engine = $this->integrationRegistry->get('stripe');

        $response = $engine->send(
            'create_payment_intent',
            body: CreatePaymentIntentBody::create([
                'amount' => $amountCents,
                'currency' => $currency,
                'metadata' => ['movie_id' => $movieId],
            ]),
        );

        \assert($response instanceof CreatePaymentIntentResponse);

        return RentalPayment::fromInfrastructure(
            paymentIntentId: $response->id(),
            movieId: $movieId,
            amountCents: $amountCents,
            currency: $currency,
            status: $response->status(),
            clientSecret: $response->clientSecret(),
        );
    }
    // tour:end
}
