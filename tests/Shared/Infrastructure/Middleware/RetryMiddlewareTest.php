<?php

declare(strict_types=1);

namespace Tests\Shared\Infrastructure\Middleware;

use App\Integrations\Supplier\GetPrices\GetPricesAction;
use App\Shared\Infrastructure\Middleware\RetryMiddleware;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class RetryMiddlewareTest extends TestCase
{
    private RetryMiddleware $middleware;

    protected function setUp(): void
    {
        $this->middleware = new RetryMiddleware();
    }

    #[Test]
    public function returnsImmediatelyOnFirstSuccess(): void
    {
        $calls = 0;
        $next = static function () use (&$calls): array {
            ++$calls;

            return ['ok' => true];
        };

        $result = $this->middleware->process($this->action(), null, null, $next);

        self::assertSame(['ok' => true], $result);
        self::assertSame(1, $calls);
    }

    #[Test]
    public function permanentErrorsAreNotRetried(): void
    {
        $calls = 0;
        $next = static function () use (&$calls): never {
            ++$calls;

            throw new \InvalidArgumentException('bad request');
        };

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('bad request');

        try {
            $this->middleware->process($this->action(), null, null, $next);
        } finally {
            self::assertSame(1, $calls);
        }
    }

    #[Test]
    public function transportErrorsAreRetriedAndCanSucceed(): void
    {
        $calls = 0;
        $next = function () use (&$calls): array {
            ++$calls;
            if ($calls < 2) {
                throw $this->transportException();
            }

            return ['ok' => true];
        };

        $result = $this->middleware->process($this->action(), null, null, $next);

        self::assertSame(['ok' => true], $result);
        self::assertSame(2, $calls);
    }

    #[Test]
    public function rateLimitAndServiceUnavailableAreRetried(): void
    {
        foreach ([429, 503, 504] as $status) {
            $calls = 0;
            $next = function () use (&$calls, $status): array {
                ++$calls;
                if ($calls < 2) {
                    throw $this->httpException($status);
                }

                return ['status' => $status];
            };

            $result = $this->middleware->process($this->action(), null, null, $next);

            self::assertSame(['status' => $status], $result, "status {$status} should be retried");
            self::assertSame(2, $calls, "status {$status} should have retried exactly once");
        }
    }

    #[Test]
    public function otherHttpStatusesAreNotRetried(): void
    {
        $calls = 0;
        $next = function () use (&$calls): never {
            ++$calls;

            throw $this->httpException(400);
        };

        try {
            $this->middleware->process($this->action(), null, null, $next);
            self::fail('Expected an exception to propagate.');
        } catch (HttpExceptionInterface) {
            self::assertSame(1, $calls);
        }
    }

    #[Test]
    public function exhaustsAllAttemptsAndRethrowsTheLastException(): void
    {
        $calls = 0;
        $next = function () use (&$calls): never {
            ++$calls;

            throw $this->transportException("attempt {$calls}");
        };

        try {
            $this->middleware->process($this->action(), null, null, $next);
            self::fail('Expected an exception to propagate.');
        } catch (TransportExceptionInterface $e) {
            self::assertSame(3, $calls);
            self::assertSame('attempt 3', $e->getMessage());
        }
    }

    private function action(): GetPricesAction
    {
        return GetPricesAction::create('GET', '/prices');
    }

    private function transportException(string $message = 'timeout'): TransportExceptionInterface
    {
        return new class ($message) extends \RuntimeException implements TransportExceptionInterface {
        };
    }

    private function httpException(int $statusCode): HttpExceptionInterface
    {
        return new class ($statusCode) extends \RuntimeException implements HttpExceptionInterface {
            public function __construct(private readonly int $statusCode)
            {
                parent::__construct("HTTP {$statusCode}");
            }

            public function getResponse(): ResponseInterface
            {
                return new class ($this->statusCode) implements ResponseInterface {
                    public function __construct(private readonly int $statusCode)
                    {
                    }

                    public function getStatusCode(): int
                    {
                        return $this->statusCode;
                    }

                    public function getHeaders(bool $throw = true): array
                    {
                        return [];
                    }

                    public function getContent(bool $throw = true): string
                    {
                        return '';
                    }

                    /** @return array<mixed> */
                    public function toArray(bool $throw = true): array
                    {
                        return [];
                    }

                    public function cancel(): void
                    {
                        // No-op: this test double has no transport to cancel.
                    }

                    public function getInfo(?string $type = null): mixed
                    {
                        return null;
                    }
                };
            }
        };
    }
}
