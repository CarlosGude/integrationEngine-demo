<?php

declare(strict_types=1);

namespace App\Billing\Application;

use App\Billing\Domain\RentalPayment;
use App\Integrations\Stripe\StripeIntegration;

final class RentalPaymentGateway
{
    public function __construct(
        private readonly StripeIntegration $stripe,
    ) {
    }

    // tour:start solution/payment-integration
    public function rentMovie(
        int $movieId,
        int $amountCents = 500,
        string $currency = 'usd',
    ): RentalPayment {
        $response = $this->stripe->createPaymentIntent(
            $amountCents,
            $currency,
            ['movie_id' => $movieId],
        );

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
