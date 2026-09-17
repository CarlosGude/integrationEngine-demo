<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Middleware;

use IntegrationEngine\Core\Contract\Client\AbstractClientMiddleware;
use IntegrationEngine\Core\Entity\PreparedRequest;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class RateLimitMiddleware extends AbstractClientMiddleware
{
    public function __construct(
        private readonly RateLimiterFactory $limiterFactory,
    ) {
    }

    // tour:start middleware-rate-limit
    public function process(PreparedRequest $request, \Closure $next): ResponseInterface
    {
        $limiter = $this->limiterFactory->create('api_requests');
        $limit = $limiter->consume();

        if (!$limit->isAccepted()) {
            throw new \RuntimeException(\sprintf('Rate limit exceeded. Retry after %d seconds.', (int) \ceil($limit->getRetryAfter()), ));
        }

        return $next($request);
    }

    /**
     * @param array<string, \Closure> $requests
     *
     * @return array<string, ResponseInterface|\Throwable>
     */
    public function processMany(array $requests, \Closure $next): array
    {
        $limiter = $this->limiterFactory->create('api_requests');

        foreach ($requests as $key => $_) {
            $limit = $limiter->consume();
            if (!$limit->isAccepted()) {
                throw new \RuntimeException('Rate limit exceeded for batch request');
            }
        }

        return $next($requests);
    }
    // tour:end middleware-rate-limit
}
