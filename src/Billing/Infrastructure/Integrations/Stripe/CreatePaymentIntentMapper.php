<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Integrations\Stripe;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class CreatePaymentIntentMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return CreatePaymentIntentAction::class;
    }

    /**
     * @param array<string, mixed> $response
     * @param array<string, list<string>> $headers
     */
    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        /** @var array{id: string, client_secret: string, status: string, amount: int, currency: string} $response */
        return new CreatePaymentIntentResponse(
            id: $response['id'],
            clientSecret: $response['client_secret'],
            status: $response['status'],
            amount: $response['amount'],
            currency: $response['currency'],
        );
    }
}
