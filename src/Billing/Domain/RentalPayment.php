<?php

declare(strict_types=1);

namespace App\Billing\Domain;

final readonly class RentalPayment
{
    public function __construct(
        public string $paymentIntentId,
        public int $movieId,
        public int $amountCents,
        public string $currency,
        public string $status,
        public string $clientSecret,
    ) {
    }

    public static function fromInfrastructure(
        string $paymentIntentId,
        int $movieId,
        int $amountCents,
        string $currency,
        string $status,
        string $clientSecret,
    ): self {
        return new self(
            paymentIntentId: $paymentIntentId,
            movieId: $movieId,
            amountCents: $amountCents,
            currency: $currency,
            status: $status,
            clientSecret: $clientSecret,
        );
    }
}
