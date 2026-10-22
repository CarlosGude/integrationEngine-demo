<?php

declare(strict_types=1);

namespace App\Tour\Application;

use App\Billing\Application\PaymentConfirmationSimulator;
use App\Billing\Application\RentalPaymentGateway;
use App\Catalog\Application\MovieCatalogGateway;
use App\Pricing\Application\PricingGateway;
use App\Shared\Infrastructure\Resilience\CircuitBreaker;
use App\Shared\Infrastructure\Resilience\FallbackStrategy;

final readonly class TourStepRunner
{
    public function __construct(
        private MovieCatalogGateway $catalog,
        private PricingGateway $pricing,
        private RentalPaymentGateway $payments,
        private PaymentConfirmationSimulator $paymentConfirmation,
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
        $movie = $this->catalog->getMovieById(550);

        return ['movie' => ['id' => $movie->id, 'title' => $movie->title]];
    }

    /** @return array<string, mixed> */
    private function parallelMovies(): array
    {
        $movies = $this->catalog->getMoviesByIdBatch([550, 278, 238, 240, 424]);

        return [
            'requested' => count($movies),
            'loaded' => count(array_filter($movies)),
            'mode' => 'sendMany',
        ];
    }

    /** @return array<string, mixed> */
    private function protocols(): array
    {
        return [
            'graphql' => ['countries' => $this->pricing->countCountries()],
            'csv' => ['prices' => $this->pricing->countSupplierPrices()],
        ];
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
        $payment = $this->payments->rentMovie(movieId: 550, amountCents: 500, currency: 'usd');

        return [
            'payment_intent_id' => $payment->paymentIntentId,
            'status' => $payment->status,
            'amount' => $payment->amountCents,
            'currency' => $payment->currency,
        ];
    }

    /** @return array<string, mixed> */
    private function paymentConfirmation(): array
    {
        return $this->paymentConfirmation->simulate(movieId: 550, amount: 500, currency: 'usd');
    }
}
