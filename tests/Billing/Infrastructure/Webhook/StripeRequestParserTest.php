<?php

declare(strict_types=1);

namespace Tests\Billing\Infrastructure\Webhook;

use App\Billing\Infrastructure\Webhook\StripeRequestParser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Webhook\Exception\RejectWebhookException;

final class StripeRequestParserTest extends TestCase
{
    private StripeRequestParser $parser;
    private string $secret = 'whsec_test_secret_123';

    protected function setUp(): void
    {
        $this->parser = new StripeRequestParser();
    }

    public function testParseValidSignature(): void
    {
        $payload = json_encode([
            'id' => 'evt_test123',
            'type' => 'payment_intent.succeeded',
            'data' => [
                'object' => [
                    'id' => 'pi_test123',
                    'status' => 'succeeded',
                    'metadata' => ['movie_id' => 550],
                ],
            ],
        ]);

        $timestamp = (string) time();
        $signed = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signed, $this->secret);

        $request = new Request(
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload,
        );
        $request->headers->set('Stripe-Signature', "t={$timestamp},v1={$signature}");

        $event = $this->parser->parse($request, $this->secret);

        self::assertNotNull($event);
        self::assertSame('payment_intent.succeeded', $event->getName());
        self::assertSame('evt_test123', $event->getId());

        $payload_decoded = $event->getPayload();
        self::assertSame('pi_test123', $payload_decoded['data']['object']['id']);
    }

    public function testRejectMissingSignature(): void
    {
        $this->expectException(RejectWebhookException::class);

        $request = new Request(
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '{"id":"evt_test"}',
        );

        $this->parser->parse($request, $this->secret);
    }

    public function testRejectInvalidSignature(): void
    {
        $this->expectException(RejectWebhookException::class);

        $payload = '{"id":"evt_test"}';
        $timestamp = (string) time();

        $request = new Request(
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload,
        );
        $request->headers->set('Stripe-Signature', "t={$timestamp},v1=invalid_signature");

        $this->parser->parse($request, $this->secret);
    }

    public function testRejectExpiredTimestamp(): void
    {
        $this->expectException(RejectWebhookException::class);

        $payload = '{"id":"evt_test"}';
        $timestamp = (string) (time() - 400); // 400 seconds ago (max allowed is 300)
        $signed = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signed, $this->secret);

        $request = new Request(
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload,
        );
        $request->headers->set('Stripe-Signature', "t={$timestamp},v1={$signature}");

        $this->parser->parse($request, $this->secret);
    }

    public function testRejectEmptyPayload(): void
    {
        $this->expectException(RejectWebhookException::class);

        $timestamp = (string) time();
        $request = new Request(
            server: ['CONTENT_TYPE' => 'application/json'],
            content: '',
        );
        $request->headers->set('Stripe-Signature', "t={$timestamp},v1=somesig");

        $this->parser->parse($request, $this->secret);
    }

    public function testRejectInvalidJson(): void
    {
        $this->expectException(RejectWebhookException::class);

        $payload = '{invalid json}';
        $timestamp = (string) time();
        $signed = "{$timestamp}.{$payload}";
        $signature = hash_hmac('sha256', $signed, $this->secret);

        $request = new Request(
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $payload,
        );
        $request->headers->set('Stripe-Signature', "t={$timestamp},v1={$signature}");

        $this->parser->parse($request, $this->secret);
    }
}
