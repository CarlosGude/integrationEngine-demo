<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Integrations\Supplier;

use IntegrationEngine\Core\Contract\Action\AbstractAction;

final class GetPricesAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'get_prices';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): ?string
    {
        return GetPricesMapper::class;
    }
}
