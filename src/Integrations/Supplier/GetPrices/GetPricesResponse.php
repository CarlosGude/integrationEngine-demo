<?php

declare(strict_types=1);

namespace App\Integrations\Supplier\GetPrices;

use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class GetPricesResponse implements ResponseInterface
{
    /**
     * @param list<array<string, string>> $prices
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
     * @return list<array<string, string>>
     */
    public function prices(): array
    {
        return $this->prices;
    }
}
