<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Middleware;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\AbstractClientMiddleware;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use Throwable;

/**
 * Exponential backoff retry middleware.
 *
 * When an API call fails, this middleware automatically retries with increasing delays.
 * Transient errors (429, 503, timeouts) are retried. Permanent errors (400, 401, 404) fail fast.
 *
 * Example: TMDB times out
 * → Retry 1: wait 100ms → try again
 * → Retry 2: wait 200ms → try again
 * → Retry 3: wait 400ms → try again
 * → If all fail: throw exception with full context
 */
final class RetryMiddleware extends AbstractClientMiddleware
{
    private const MAX_ATTEMPTS = 3;
    private const INITIAL_BACKOFF_MS = 100;

    public function process(
        AbstractAction $action,
        ?ActionContextInterface $context,
        ?RequestHeadersInterface $headers,
        callable $next,
    ): array {
        $lastException = null;

        // tour:start resilience/retry-exponential-backoff
        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; ++$attempt) {
            try {
                return $next($action, $context, $headers);
            } catch (\Throwable $e) {
                $lastException = $e;

                // Distinguish transient vs permanent failures
                if (!$this->isTransient($e)) {
                    throw $e; // Don't retry client/auth errors
                }

                if ($attempt < self::MAX_ATTEMPTS) {
                    // Exponential backoff: 100ms, 200ms, 400ms
                    $backoffMs = self::INITIAL_BACKOFF_MS * (2 ** ($attempt - 1));
                    usleep($backoffMs * 1000);
                }
            }
        }

        throw $lastException ?? new \RuntimeException('Retry exhausted');
        // tour:end
    }

    /**
     * Classify error as transient (retryable) or permanent.
     * tour:start resilience/error-classification
     */
    private function isTransient(\Throwable $e): bool
    {
        // Network errors → retryable
        if ($e instanceof \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface) {
            return true;
        }

        // Rate limit (429) → retryable
        if ($e instanceof \Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface) {
            $code = $e->getResponse()->getStatusCode();

            return \in_array($code, [429, 503, 504], true);
        }

        // Everything else → permanent
        return false;
    }
    // tour:end
}
