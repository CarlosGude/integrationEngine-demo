<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Middleware;

use App\Shared\Infrastructure\Middleware\RateLimitMiddleware;
use IntegrationEngine\Core\Contract\Client\AbstractClientMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RateLimitMiddlewareTest extends TestCase
{
    #[Test]
    public function rateLimitMiddlewareExtendsAbstractClientMiddleware(): void
    {
        self::assertTrue(\is_subclass_of(RateLimitMiddleware::class, AbstractClientMiddleware::class));
    }

    #[Test]
    public function rateLimitMiddlewareImplementsProcessMethod(): void
    {
        self::assertTrue(\method_exists(RateLimitMiddleware::class, 'process'));
    }

    #[Test]
    public function rateLimitMiddlewareImplementsProcessManyMethod(): void
    {
        self::assertTrue(\method_exists(RateLimitMiddleware::class, 'processMany'));
    }
}
