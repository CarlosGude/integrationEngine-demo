<?php

declare(strict_types=1);

namespace App\Integrations\Stripe\Webhook;

use App\Integrations\Stripe\Webhook\Mapper\StripePaymentIntentMapper;
use IntegrationEngine\Core\Contract\Webhook\AbstractWebhookMapper;
use IntegrationEngine\Core\Contract\Webhook\SignatureVerifierInterface;
use IntegrationEngine\Core\Webhook\TimestampedHmacSignatureVerifier;
use IntegrationEngine\Infrastructure\Webhook\IntegrationWebhookRequestParser;
use Psr\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// tour:start webhook/parser
/**
 * Registered under framework.webhook.routing.stripe: POST /webhook/stripe.
 *
 * Stripe signs "t={timestamp},v1={hmac}" — the timestamped HMAC scheme,
 * not a plain "sha256=" HMAC.
 */
final class StripePaymentIntentParser extends IntegrationWebhookRequestParser
{
    private const SIGNATURE_HEADER = 'Stripe-Signature';
    private const SIGNATURE_TOLERANCE_SECONDS = 300;

    public function __construct(
        private readonly ClockInterface $clock,
        #[Autowire(env: 'STRIPE_WEBHOOK_SECRET')]
        #[\SensitiveParameter]
        private readonly string $secret,
    ) {
    }

    public function getDefinition(): string
    {
        return 'payment_intent.succeeded';
    }

    public function getMapper(): AbstractWebhookMapper
    {
        return new StripePaymentIntentMapper();
    }

    protected function getSignatureVerifier(): SignatureVerifierInterface
    {
        return new TimestampedHmacSignatureVerifier(
            self::SIGNATURE_HEADER,
            self::SIGNATURE_TOLERANCE_SECONDS,
            $this->clock,
        );
    }

    protected function getSignatureSecret(): string
    {
        return $this->secret;
    }
}
// tour:end
