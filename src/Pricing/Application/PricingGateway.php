<?php

declare(strict_types=1);

namespace App\Pricing\Application;

use App\Integrations\Countries\CountriesIntegration;
use App\Integrations\Supplier\SupplierIntegration;

final readonly class PricingGateway
{
    public function __construct(
        private CountriesIntegration $countries,
        private SupplierIntegration $supplier,
    ) {
    }

    public function countCountries(): int
    {
        return count($this->countries->getCountries()->countries());
    }

    public function countSupplierPrices(): int
    {
        return count($this->supplier->getPrices()->prices());
    }
}
