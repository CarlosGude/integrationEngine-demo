<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Integrations\Stripe;

use IntegrationEngine\Core\Contract\Action\AbstractAction;

// tour:start solution/payment-action
final class CreatePaymentIntentAction extends AbstractAction
{
    public static function getName(): string
    {
        return 'create_payment_intent';
    }

    public static function hasResponse(): bool
    {
        return true;
    }

    public static function mapper(): ?string
    {
        return CreatePaymentIntentMapper::class;
    }
}
// tour:end
