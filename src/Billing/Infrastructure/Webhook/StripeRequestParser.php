<?php

declare(strict_types=1);

namespace App\Billing\Infrastructure\Webhook;

use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestMatcher\HeaderRequestMatcher;
use Symfony\Component\HttpFoundation\RequestMatcherInterface;
use Symfony\Component\RemoteEvent\RemoteEvent;
use Symfony\Component\Webhook\Client\AbstractRequestParser;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class StripeRequestParser extends AbstractRequestParser
{
    private const MAX_TIMESTAMP_AGE = 300; // 5 minutes

    protected function getRequestMatcher(): RequestMatcherInterface
    {
        return new HeaderRequestMatcher('Stripe-Signature');
    }

    /**
     * @param string $secret Stripe webhook signing secret (whsec_...)
     *
     * @return RemoteEvent|null
     *
     * @throws RejectWebhookException on signature validation failure
     */
    protected function doParse(Request $request, #[\SensitiveParameter] string $secret): RemoteEvent|array|null
    {
        $signature = $request->headers->get('Stripe-Signature');
        if (!$signature) {
            throw new RejectWebhookException(406, 'Missing Stripe-Signature header.');
        }

        $payload = $request->getContent();
        if (!$payload) {
            throw new RejectWebhookException(406, 'Empty request body.');
        }

        $this->validateSignature($signature, $payload, $secret);

        $data = json_decode($payload, associative: true);
        if (!\is_array($data)) {
            throw new RejectWebhookException(406, 'Invalid JSON payload.');
        }

        return new RemoteEvent(
            name: (string) ($data['type'] ?? 'unknown'),
            id: (string) ($data['id'] ?? ''),
            payload: $data,
        );
    }

    // tour:start webhook/parser
    /**
     * Validates Stripe's signed header format: t=<timestamp>,v1=<signature>[,v0=...]
     * Computes HMAC-SHA256 of "{timestamp}.{payload}" using the secret.
     *
     * @throws RejectWebhookException on invalid or expired signature
     */
    private function validateSignature(string $signatureHeader, string $payload, #[\SensitiveParameter] string $secret): void
    {
        $pairs = explode(',', $signatureHeader);
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
            throw new RejectWebhookException(406, 'Malformed Stripe-Signature header.');
        }

        $now = (int) time();
        $ts = (int) $timestamp;
        if (abs($now - $ts) > self::MAX_TIMESTAMP_AGE) {
            throw new RejectWebhookException(406, 'Stripe webhook timestamp expired.');
        }

        $signed = "{$timestamp}.{$payload}";
        $expected = hash_hmac('sha256', $signed, $secret);

        if (!hash_equals($expected, $signatureV1)) {
            throw new RejectWebhookException(406, 'Invalid Stripe-Signature.');
        }
    }
    // tour:end
}
