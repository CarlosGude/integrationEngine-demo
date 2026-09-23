<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Middleware;

use IntegrationEngine\Core\Batch\PreparedRequest;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\AbstractClientMiddleware;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;

final class RateLimitExceededException extends \RuntimeException
{
}

final class RateLimitMiddleware extends AbstractClientMiddleware
{
    private static int $requestsThisSecond = 0;
    private static int $lastSecond = 0;
    private const MAX_REQUESTS_PER_SECOND = 40;

    // tour:start middleware-rate-limit
    public function process(
        AbstractAction $action,
        ?ActionContextInterface $context,
        ?RequestHeadersInterface $headers,
        callable $next,
    ): array {
        $this->checkRateLimit();

        return $next($action, $context, $headers);
    }

    /**
     * @param array<array-key, PreparedRequest> $requests
     *
     * @return array<array-key, array<mixed>|\Throwable>
     */
    public function processMany(array $requests, callable $next): array
    {
        for ($i = 0, $count = count($requests); $i < $count; ++$i) {
            $this->checkRateLimit();
        }

        return $next($requests);
    }

    private function checkRateLimit(): void
    {
        $now = (int) time();
        if ($now > self::$lastSecond) {
            self::$requestsThisSecond = 0;
            self::$lastSecond = $now;
        }

        ++self::$requestsThisSecond;
        if (self::$requestsThisSecond > self::MAX_REQUESTS_PER_SECOND) {
            throw new RateLimitExceededException('Rate limit exceeded (40 requests/second max)');
        }
    }
    // tour:end middleware-rate-limit
}
