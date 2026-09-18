<?php

declare(strict_types=1);

namespace App\Integrations\Supplier;

use App\Integrations\Supplier\GetPrices\GetPricesResponse;
use IntegrationEngine\Core\Registry\IntegrationRegistry;

final readonly class SupplierIntegration
{
    public function __construct(
        private IntegrationRegistry $registry,
    ) {
    }

    public function getPrices(): GetPricesResponse
    {
        $engine = $this->registry->get('supplier');
        $response = $engine->send('get_prices');
        \assert($response instanceof GetPricesResponse);

        return $response;
    }
}
