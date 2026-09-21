<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure;

use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Chaos Monkey: Inject failures for testing resilience.
 *
 * Simulates real-world scenarios:
 * - Network timeouts
 * - Rate limiting (429)
 * - Service unavailable (503)
 *
 * Example: With 30% rate_limit_failure, ~3 in 10 requests will fail with 429.
 *
 * tour:start resilience/chaos-monkey-injection
 */
final class ChaosMonkey
{
    public function __construct(
        private readonly int $seed = 42,
        private int $timeoutFailureRate = 0,        // 0-100: % timeout failures
        private int $rateLimitFailureRate = 0,      // 0-100: % rate limit (429)
        private int $serviceUnavailableRate = 0,    // 0-100: % service unavailable (503)
    ) {
        if ($seed >= 0) {
            mt_srand($seed);
        }
    }

    /**
     * Should this request fail with a timeout?
     */
    public function shouldTimeout(): bool
    {
        return $this->shouldFail($this->timeoutFailureRate);
    }

    /**
     * Should this request fail with rate limiting (429)?
     */
    public function shouldRateLimit(): bool
    {
        return $this->shouldFail($this->rateLimitFailureRate);
    }

    /**
     * Should this request fail with service unavailable (503)?
     */
    public function shouldServiceFail(): bool
    {
        return $this->shouldFail($this->serviceUnavailableRate);
    }

    /**
     * Inject a timeout exception.
     */
    public function injectTimeout(): never
    {
        throw new class ('Request timeout (simulated)') extends \RuntimeException implements TransportExceptionInterface {
            public function getCode(): int
            {
                return 0;
            }
        };
    }

    /**
     * Inject a rate limit exception (429).
     */
    public function injectRateLimit(): never
    {
        throw new class ('Too Many Requests (simulated)', 429) extends \Exception implements HttpExceptionInterface {
            public function getResponse(): ResponseInterface
            {
                return new class () implements ResponseInterface {
                    public function getStatusCode(): int
                    {
                        return 429;
                    }

                    public function getHeaders(bool $throw = true): array
                    {
                        return [];
                    }

                    public function getContent(bool $throw = true): string
                    {
                        return '';
                    }

                    /**
                     * @return array<mixed>
                     */
                    public function toArray(bool $throw = true): array
                    {
                        return [];
                    }

                    public function cancel(): void
                    {
                    }

                    public function getInfo(?string $type = null): mixed
                    {
                        return null;
                    }
                };
            }
        };
    }

    /**
     * Inject a service unavailable exception (503).
     */
    public function injectServiceUnavailable(): never
    {
        throw new class ('Service Unavailable (simulated)', 503) extends \Exception implements HttpExceptionInterface {
            public function getResponse(): ResponseInterface
            {
                return new class () implements ResponseInterface {
                    public function getStatusCode(): int
                    {
                        return 503;
                    }

                    public function getHeaders(bool $throw = true): array
                    {
                        return [];
                    }

                    public function getContent(bool $throw = true): string
                    {
                        return '';
                    }

                    /**
                     * @return array<mixed>
                     */
                    public function toArray(bool $throw = true): array
                    {
                        return [];
                    }

                    public function cancel(): void
                    {
                    }

                    public function getInfo(?string $type = null): mixed
                    {
                        return null;
                    }
                };
            }
        };
    }

    public function withTimeoutRate(int $rate): self
    {
        $this->timeoutFailureRate = $rate;

        return $this;
    }

    public function withRateLimitRate(int $rate): self
    {
        $this->rateLimitFailureRate = $rate;

        return $this;
    }

    public function withServiceUnavailableRate(int $rate): self
    {
        $this->serviceUnavailableRate = $rate;

        return $this;
    }

    private function shouldFail(int $rate): bool
    {
        if ($rate <= 0 || $rate >= 100) {
            return $rate > 50; // Deterministic for edge cases
        }

        return mt_rand(1, 100) <= $rate;
    }
    // tour:end
}
