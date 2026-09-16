<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Kernel;
use PHPUnit\Framework\TestCase;

final class KernelBootTest extends TestCase
{
    public function testKernelBootsSuccessfully(): void
    {
        $kernel = new Kernel('test', true);
        $kernel->boot();

        self::assertTrue($kernel->isBooted());
    }

    public function testKernelContainerHasRequiredServices(): void
    {
        $kernel = new Kernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();

        self::assertTrue($container->has('service_container'));
        self::assertTrue($container->has('integration_engine.registry'));
    }
}
