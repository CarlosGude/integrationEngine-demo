<?php

declare(strict_types=1);

namespace Tests\Shared\Observability\Lifecycle;

use App\Shared\Observability\Lifecycle\StatusClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;

final class StatusClassTest extends TestCase
{
    #[Test]
    #[TestWith([200, '2xx'])]
    #[TestWith([301, '3xx'])]
    #[TestWith([404, '4xx'])]
    #[TestWith([503, '5xx'])]
    #[TestWith([0, 'network'])]
    #[TestWith([199, 'other'])]
    #[TestWith([599, '5xx'])]
    #[TestWith([600, 'other'])]
    #[TestWith([300, '3xx'])]
    #[TestWith([400, '4xx'])]
    #[TestWith([500, '5xx'])]
    public function fromCodeClassifiesStatusCodes(int $statusCode, string $expected): void
    {
        self::assertSame($expected, StatusClass::fromCode($statusCode));
    }
}
