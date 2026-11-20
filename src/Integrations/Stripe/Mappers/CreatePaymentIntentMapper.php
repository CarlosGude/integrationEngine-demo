<?php

declare(strict_types=1);

namespace App\Integrations\Stripe\Mappers;

use App\Integrations\Stripe\CreatePaymentIntent\CreatePaymentIntentAction;
use App\Integrations\Stripe\CreatePaymentIntent\CreatePaymentIntentResponse;
use App\Shared\Infrastructure\Integration\ResponseFields;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class CreatePaymentIntentMapper extends AbstractMapper
{
    private const SOURCE = 'Stripe create_payment_intent';

    public static function getAction(): string
    {
        return CreatePaymentIntentAction::class;
    }

    /**
     * @param array<string, mixed>        $response
     * @param array<string, list<string>> $headers
     */
    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        return new CreatePaymentIntentResponse(
            id: ResponseFields::string($response, 'id', self::SOURCE),
            clientSecret: ResponseFields::string($response, 'client_secret', self::SOURCE),
            status: ResponseFields::string($response, 'status', self::SOURCE),
            amount: ResponseFields::int($response, 'amount', self::SOURCE),
            currency: ResponseFields::string($response, 'currency', self::SOURCE),
        );
    }
}
