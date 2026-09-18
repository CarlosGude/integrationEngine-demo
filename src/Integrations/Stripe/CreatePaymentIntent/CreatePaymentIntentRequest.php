<?php

declare(strict_types=1);

namespace App\Integrations\Stripe\CreatePaymentIntent;

use IntegrationEngine\Core\Contract\Action\ActionBodyInterface;

final class CreatePaymentIntentRequest implements ActionBodyInterface
{
    private function __construct(
        private readonly int $amount,
        private readonly string $currency,
        /** @var array<string, mixed> */
        private readonly array $metadata = [],
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function create(array $data): self
    {
        return new self(
            amount: (int) ($data['amount'] ?? 0),
            currency: (string) ($data['currency'] ?? 'usd'),
            metadata: (array) ($data['metadata'] ?? []),
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'metadata' => $this->metadata,
        ];
    }
}
