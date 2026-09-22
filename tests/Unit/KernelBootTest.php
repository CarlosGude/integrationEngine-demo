<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Kernel;
use IntegrationEngine\Core\Registry\IntegrationRegistry;
use PHPUnit\Framework\TestCase;

final class KernelBootTest extends TestCase
{
    public function testKernelBootsSuccessfully(): void
    {
        $kernel = new Kernel('test', true);
        $kernel->boot();

        /** @phpstan-ignore staticMethod.alreadyNarrowedType */
        self::assertNotNull($kernel->getContainer());
    }

    public function testKernelContainerHasRequiredServices(): void
    {
        $kernel = new Kernel('test', true);
        $kernel->boot();

        $container = $kernel->getContainer();
        $testContainer = $container->get('test.service_container');
        \assert($testContainer instanceof \Symfony\Component\DependencyInjection\ContainerInterface);

        self::assertTrue($container->has('service_container'));
        self::assertTrue($testContainer->has(IntegrationRegistry::class));
    }
}
