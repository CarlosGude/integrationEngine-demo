<?php

declare(strict_types=1);

namespace Tests\Billing\Application;

use App\Billing\Application\PaymentConfirmationSimulator;
use App\Integrations\Stripe\Webhook\StripePaymentIntentEvent;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;

final class PaymentConfirmationSimulatorTest extends TestCase
{
    public function testDispatchesTypedPaymentEventAndReturnsDemoProjection(): void
    {
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects(self::once())
            ->method('dispatch')
            ->with(self::callback(static function (object $event): bool {
                return $event instanceof StripePaymentIntentEvent
                    && 'evt_demo' === $event->eventId
                    && 'payment_intent.succeeded' === $event->eventType
                    && 'pi_demo' === $event->paymentIntentId
                    && 550 === $event->movieId
                    && 500 === $event->amount
                    && 'usd' === $event->currency;
            }))
            ->willReturnArgument(0);

        $simulator = new PaymentConfirmationSimulator($dispatcher);

        self::assertSame([
            'event' => 'payment_intent.succeeded',
            'payment_intent_id' => 'pi_demo',
            'mercure_topic' => 'admin/payments',
            'mode' => 'typed-event downstream simulation',
        ], $simulator->simulate(550, 500, 'usd'));
    }
}
