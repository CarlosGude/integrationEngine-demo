<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Integrations\Stripe;

use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class CreatePaymentIntentResponse implements ResponseInterface
{
    public function __construct(
        private readonly string $id,
        private readonly string $clientSecret,
        private readonly string $status,
        private readonly int $amount,
        private readonly string $currency,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'client_secret' => $this->clientSecret,
            'status' => $this->status,
            'amount' => $this->amount,
            'currency' => $this->currency,
        ];
    }

    public function id(): string
    {
        return $this->id;
    }

    public function clientSecret(): string
    {
        return $this->clientSecret;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function currency(): string
    {
        return $this->currency;
    }
}
