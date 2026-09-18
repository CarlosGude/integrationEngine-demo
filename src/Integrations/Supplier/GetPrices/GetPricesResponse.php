<?php

declare(strict_types=1);

namespace App\Integrations\Supplier\GetPrices;

use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class GetPricesResponse implements ResponseInterface
{
    /**
     * @param array<int, array{sku: string, price: string, currency: string}> $prices
     */
    public function __construct(
        private readonly array $prices,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['prices' => $this->prices];
    }

    /**
     * @return array<int, array{sku: string, price: string, currency: string}>
     */
    public function prices(): array
    {
        return $this->prices;
    }
}
