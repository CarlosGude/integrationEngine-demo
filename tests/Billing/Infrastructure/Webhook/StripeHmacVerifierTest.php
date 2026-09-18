<?php

declare(strict_types=1);

namespace Tests\Billing\Infrastructure\Webhook;

use App\Billing\Infrastructure\Webhook\StripeHmacVerifier;
use PHPUnit\Framework\TestCase;

final class StripeHmacVerifierTest extends TestCase
{
    private StripeHmacVerifier $verifier;
    private string $secret = 'whsec_test_secret_123';

    protected function setUp(): void
    {
        $this->verifier = new StripeHmacVerifier();
    }

    public function testVerifyValidSignature(): void
    {
        $payload = json_encode([
            'id' => 'evt_test123',
            'type' => 'payment_intent.succeeded',
            'data' => ['object' => ['id' => 'pi_test123']],
        ]);

        $timestamp = (string) time();
        $signed = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signed, $this->secret);

        $headerValue = "t={$timestamp},v1={$signature}";

        self::assertTrue($this->verifier->verify($payload, $headerValue, $this->secret));
    }

    public function testRejectInvalidSignature(): void
    {
        $payload = '{"id":"evt_test"}';
        $timestamp = (string) time();

        $headerValue = "t={$timestamp},v1=invalid_signature";

        self::assertFalse($this->verifier->verify($payload, $headerValue, $this->secret));
    }

    public function testRejectExpiredTimestamp(): void
    {
        $payload = '{"id":"evt_test"}';
        $timestamp = (string) (time() - 400); // 400 seconds ago (max allowed is 300)
        $signed = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signed, $this->secret);

        $headerValue = "t={$timestamp},v1={$signature}";

        self::assertFalse($this->verifier->verify($payload, $headerValue, $this->secret));
    }

    public function testRejectMalformedHeader(): void
    {
        $payload = '{"id":"evt_test"}';
        $headerValue = 't=xyz,v1=abc';

        self::assertFalse($this->verifier->verify($payload, $headerValue, $this->secret));
    }

    public function testGetHeaderName(): void
    {
        self::assertSame('Stripe-Signature', $this->verifier->getHeaderName());
    }
}
