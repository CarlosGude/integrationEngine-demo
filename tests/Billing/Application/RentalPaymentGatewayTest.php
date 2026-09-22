<?php

declare(strict_types=1);

namespace Tests\Billing\Application;

use App\Billing\Application\RentalPaymentGateway;
use App\Billing\Domain\RentalPayment;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RentalPaymentGatewayTest extends KernelTestCase
{
    #[Test]
    public function rentMovieBuildsARentalPaymentFromTheStripeResponse(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $stripeJson = json_encode([
            'id' => 'pi_abc123',
            'client_secret' => 'pi_abc123_secret_xyz',
            'status' => 'requires_payment_method',
            'amount' => 750,
            'currency' => 'eur',
        ], \JSON_THROW_ON_ERROR);
        $container->set('http_client', new MockHttpClient([new MockResponse($stripeJson, ['http_code' => 200])]));

        $gateway = $container->get(RentalPaymentGateway::class);
        \assert($gateway instanceof RentalPaymentGateway);

        $payment = $gateway->rentMovie(movieId: 550, amountCents: 750, currency: 'eur');

        self::assertInstanceOf(RentalPayment::class, $payment);
        self::assertSame('pi_abc123', $payment->paymentIntentId);
        self::assertSame(550, $payment->movieId);
        self::assertSame(750, $payment->amountCents);
        self::assertSame('eur', $payment->currency);
        self::assertSame('requires_payment_method', $payment->status);
        self::assertSame('pi_abc123_secret_xyz', $payment->clientSecret);
    }

    #[Test]
    public function rentMovieDefaultsToFiveHundredCentsAndUsd(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $stripeJson = json_encode([
            'id' => 'pi_def456',
            'client_secret' => 'secret',
            'status' => 'requires_payment_method',
            'amount' => 500,
            'currency' => 'usd',
        ], \JSON_THROW_ON_ERROR);
        $container->set('http_client', new MockHttpClient([new MockResponse($stripeJson, ['http_code' => 200])]));

        $gateway = $container->get(RentalPaymentGateway::class);
        \assert($gateway instanceof RentalPaymentGateway);

        $payment = $gateway->rentMovie(movieId: 550);

        self::assertSame(500, $payment->amountCents);
        self::assertSame('usd', $payment->currency);
    }

    #[Test]
    public function rentMoviePropagatesHttpFailures(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $container->set('http_client', new MockHttpClient([
            new MockResponse('{"error":{"message":"card declined"}}', ['http_code' => 402]),
        ]));

        $gateway = $container->get(RentalPaymentGateway::class);
        \assert($gateway instanceof RentalPaymentGateway);

        $this->expectException(\Throwable::class);
        $gateway->rentMovie(movieId: 550);
    }
}
