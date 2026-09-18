<?php

declare(strict_types=1);

namespace Tests\Billing\Infrastructure\Integrations\Stripe;

use App\Integrations\Stripe\CreatePaymentIntent\CreatePaymentIntentAction;
use App\Integrations\Stripe\Mappers\CreatePaymentIntentMapper;
use App\Integrations\Stripe\CreatePaymentIntent\CreatePaymentIntentResponse;
use PHPUnit\Framework\TestCase;

final class CreatePaymentIntentMapperTest extends TestCase
{
    public function testMapValidResponse(): void
    {
        $action = CreatePaymentIntentAction::create('POST', '/v1/payment_intents');
        $response = [
            'id' => 'pi_test123',
            'client_secret' => 'pi_test123_secret',
            'status' => 'requires_payment_method',
            'amount' => 500,
            'currency' => 'usd',
        ];
        $headers = [];

        $result = CreatePaymentIntentMapper::map($action, $response, $headers);

        self::assertInstanceOf(CreatePaymentIntentResponse::class, $result);
        self::assertSame('pi_test123', $result->id());
        self::assertSame('pi_test123_secret', $result->clientSecret());
        self::assertSame('requires_payment_method', $result->status());
        self::assertSame(500, $result->amount());
        self::assertSame('usd', $result->currency());
    }

    public function testMapResponseToArray(): void
    {
        $action = CreatePaymentIntentAction::create('POST', '/v1/payment_intents');
        $response = [
            'id' => 'pi_test456',
            'client_secret' => 'pi_test456_secret',
            'status' => 'succeeded',
            'amount' => 1000,
            'currency' => 'eur',
        ];

        $result = CreatePaymentIntentMapper::map($action, $response, []);

        $array = $result->toArray();
        self::assertSame([
            'id' => 'pi_test456',
            'client_secret' => 'pi_test456_secret',
            'status' => 'succeeded',
            'amount' => 1000,
            'currency' => 'eur',
        ], $array);
    }
}
