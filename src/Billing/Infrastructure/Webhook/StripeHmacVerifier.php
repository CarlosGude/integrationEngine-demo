<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Webhook;

use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;

// tour:start webhook/verifier
final readonly class StripeHmacVerifier implements SignatureVerifierInterface
{
    private const MAX_TIMESTAMP_AGE = 300; // 5 minutes

    public function verify(string $body, string $signature, #[\SensitiveParameter] string $secret): bool
    {
        // Parse Stripe-Signature: t=<timestamp>,v1=<signature>[,v0=...]
        $pairs = explode(',', $signature);
        $timestamp = null;
        $signatureV1 = null;

        foreach ($pairs as $pair) {
            [$key, $value] = array_pad(explode('=', $pair, 2), 2, null);
            if ('t' === $key) {
                $timestamp = $value;
            } elseif ('v1' === $key) {
                $signatureV1 = $value;
            }
        }

        if (!$timestamp || !$signatureV1) {
            return false;
        }

        $now = (int) time();
        $ts = (int) $timestamp;
        if (abs($now - $ts) > self::MAX_TIMESTAMP_AGE) {
            return false;
        }

        $signed = "{$timestamp}.{$body}";
        $expected = hash_hmac('sha256', $signed, $secret);

        return hash_equals($expected, $signatureV1);
    }

    public function getHeaderName(): string
    {
        return 'Stripe-Signature';
    }
}
// tour:end
