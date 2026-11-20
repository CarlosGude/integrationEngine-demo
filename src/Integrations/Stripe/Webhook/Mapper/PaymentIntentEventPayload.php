<?php

declare(strict_types=1);

namespace App\Integrations\Stripe\Webhook\Mapper;

use App\Integrations\Stripe\Webhook\StripePaymentIntentEvent;
use App\Shared\Infrastructure\Integration\ResponseFields;
use App\Shared\Infrastructure\Integration\UnexpectedApiResponse;

// tour:start webhook/mapper
/**
 * Maps the payload shared by every payment_intent.* event; each event mapper delegates here.
 */
final class PaymentIntentEventPayload
{
    /** @param array<mixed> $payload */
    public static function toEvent(array $payload): StripePaymentIntentEvent
    {
        $source = 'Stripe webhook';
        $paymentIntent = ResponseFields::array(ResponseFields::array($payload, 'data', $source), 'object', $source.' data');
        $objectSource = $source.' data.object';

        return new StripePaymentIntentEvent(
            eventId: ResponseFields::string($payload, 'id', $source),
            eventType: ResponseFields::string($payload, 'type', $source),
            paymentIntentId: ResponseFields::string($paymentIntent, 'id', $objectSource),
            movieId: self::movieId($paymentIntent, $objectSource),
            status: ResponseFields::string($paymentIntent, 'status', $objectSource),
            amount: ResponseFields::int($paymentIntent, 'amount', $objectSource),
            currency: ResponseFields::string($paymentIntent, 'currency', $objectSource),
        );
    }

    /**
     * metadata.movie_id is set by this application, so it may be absent on payments created
     * elsewhere; when present, Stripe returns it as a string.
     *
     * @param array<mixed> $paymentIntent
     */
    private static function movieId(array $paymentIntent, string $source): ?int
    {
        $metadata = $paymentIntent['metadata'] ?? null;
        $movieId = \is_array($metadata) ? ($metadata['movie_id'] ?? null) : null;

        if ($movieId === null) {
            return null;
        }

        if (\is_int($movieId)) {
            return $movieId;
        }

        if (\is_string($movieId) && ctype_digit($movieId)) {
            return (int) $movieId;
        }

        throw UnexpectedApiResponse::invalidField($source, 'metadata.movie_id', 'a numeric movie id');
    }
}
// tour:end
