<?php

declare(strict_types=1);

namespace App\Tour\Application;

use App\Billing\Application\RentalPaymentGateway;
use App\Catalog\Application\MovieCatalogGateway;
use App\Integrations\Countries\CountriesIntegration;
use App\Integrations\Stripe\Webhook\StripePaymentIntentEvent;
use App\Integrations\Supplier\SupplierIntegration;
use App\Shared\Infrastructure\Resilience\CircuitBreaker;
use App\Shared\Infrastructure\Resilience\FallbackStrategy;
use App\Shared\Observability\TraceRecorderMiddleware;
use Psr\EventDispatcher\EventDispatcherInterface;

final readonly class TourStepRunner
{
    public function __construct(
        private MovieCatalogGateway $catalog,
        private CountriesIntegration $countries,
        private SupplierIntegration $supplier,
        private RentalPaymentGateway $payments,
        private EventDispatcherInterface $events,
    ) {
    }

    /** @return array<string, mixed> */
    public function run(string $stepId): array
    {
        return match ($stepId) {
            'the-problem' => $this->singleMovie(),
            'parallel-requests' => $this->parallelMovies(),
            'behind-the-counter' => $this->protocols(),
            'when-suppliers-fail' => $this->circuitBreaker(),
            'graceful-degradation' => $this->fallbacks(),
            'renting-a-movie' => $this->rentMovie(),
            'payment-confirmation' => $this->paymentConfirmation(),
            default => throw new \InvalidArgumentException(sprintf('Step "%s" is not executable.', $stepId)),
        };
    }

    /** @return array<string, mixed> */
    private function singleMovie(): array
    {
        return $this->timed('tmdb.get_movie', 'GET', function (): array {
            $movie = $this->catalog->getMovieById(550);

            return ['movie' => ['id' => $movie->id, 'title' => $movie->title]];
        });
    }

    /** @return array<string, mixed> */
    private function parallelMovies(): array
    {
        return $this->timed('tmdb.get_movies_batch', 'GET', function (): array {
            $movies = $this->catalog->getMoviesByIdBatch([550, 278, 238, 240, 424]);

            return [
                'requested' => count($movies),
                'loaded' => count(array_filter($movies)),
                'mode' => 'sendMany',
            ];
        });
    }

    /** @return array<string, mixed> */
    private function protocols(): array
    {
        $countries = $this->timed('countries.get_countries', 'POST', fn (): array => [
            'countries' => count($this->countries->getCountries()->countries()),
        ]);
        $supplier = $this->timed('supplier.get_prices', 'GET', fn (): array => [
            'prices' => count($this->supplier->getPrices()->prices()),
        ]);

        return ['graphql' => $countries, 'csv' => $supplier];
    }

    /** @return array<string, mixed> */
    private function circuitBreaker(): array
    {
        $breaker = new CircuitBreaker();
        for ($i = 0; $i < 5; ++$i) {
            $breaker->recordFailure();
        }

        return [
            'state_after_failures' => $breaker->getState(),
            'request_allowed' => $breaker->allow(),
        ];
    }

    /** @return array<string, mixed> */
    private function fallbacks(): array
    {
        return [
            'null' => FallbackStrategy::nullFallback(),
            'cache' => FallbackStrategy::cacheFallback(['price' => '2.99', 'currency' => 'USD']),
            'default' => FallbackStrategy::defaultFallback(['stock' => 0]),
        ];
    }

    /** @return array<string, mixed> */
    private function rentMovie(): array
    {
        return $this->timed('stripe.create_payment_intent', 'POST', function (): array {
            $payment = $this->payments->rentMovie(movieId: 550, amountCents: 500, currency: 'usd');

            return [
                'payment_intent_id' => $payment->paymentIntentId,
                'status' => $payment->status,
                'amount' => $payment->amountCents,
                'currency' => $payment->currency,
            ];
        });
    }

    /** @return array<string, mixed> */
    private function paymentConfirmation(): array
    {
        $event = new StripePaymentIntentEvent(
            eventId: 'evt_demo',
            eventType: 'payment_intent.succeeded',
            paymentIntentId: 'pi_demo',
            movieId: 550,
            status: 'succeeded',
            amount: 500,
            currency: 'usd',
        );

        $this->events->dispatch($event);

        return [
            'event' => $event->eventType,
            'payment_intent_id' => $event->paymentIntentId,
            'mercure_topic' => 'admin/payments',
            'mode' => 'typed-event downstream simulation',
        ];
    }

    /**
     * @param callable(): array<string, mixed> $operation
     *
     * @return array<string, mixed>
     */
    private function timed(string $action, string $method, callable $operation): array
    {
        $started = microtime(true);

        try {
            $result = $operation();
            TraceRecorderMiddleware::recordCall($action, $method, 200, (microtime(true) - $started) * 1000);

            return $result;
        } catch (\Throwable $e) {
            TraceRecorderMiddleware::recordCall($action, $method, 0, (microtime(true) - $started) * 1000);

            throw $e;
        }
    }
}
