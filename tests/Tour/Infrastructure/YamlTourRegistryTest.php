<?php

declare(strict_types=1);

namespace Tests\Tour\Infrastructure;

use App\Tour\Domain\StepNotFoundException;
use App\Tour\Infrastructure\YamlTourRegistry;
use PHPUnit\Framework\TestCase;

final class YamlTourRegistryTest extends TestCase
{
    private YamlTourRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new YamlTourRegistry(__DIR__.'/../../Fixtures/tour.yaml');
    }

    public function testGetAllStepsReturnsSortedByOrder(): void
    {
        $steps = $this->registry->getAllSteps();

        self::assertCount(3, $steps);
        self::assertSame('step1', $steps[0]->id);
        self::assertSame('step2', $steps[1]->id);
        self::assertSame('step3', $steps[2]->id);
    }

    public function testGetStepReturnsExistingStep(): void
    {
        $step = $this->registry->getStep('step2');

        self::assertSame('step2', $step->id);
        self::assertSame('The Problem', $step->title);
    }

    public function testGetStepThrowsForUnknownStep(): void
    {
        self::expectException(StepNotFoundException::class);
        $this->registry->getStep('unknown');
    }

    public function testGetNextStepWhenNotLast(): void
    {
        $nextStep = $this->registry->getNextStep('step1');

        self::assertNotNull($nextStep);
        self::assertSame('step2', $nextStep->id);
    }

    public function testGetNextStepReturnsNullWhenLast(): void
    {
        $nextStep = $this->registry->getNextStep('step3');

        self::assertNull($nextStep);
    }

    public function testGetPreviousStepWhenNotFirst(): void
    {
        $prevStep = $this->registry->getPreviousStep('step2');

        self::assertNotNull($prevStep);
        self::assertSame('step1', $prevStep->id);
    }

    public function testGetPreviousStepReturnsNullWhenFirst(): void
    {
        $prevStep = $this->registry->getPreviousStep('step1');

        self::assertNull($prevStep);
    }

    public function testNavigationThrowsForUnknownStep(): void
    {
        self::expectException(StepNotFoundException::class);
        $this->registry->getNextStep('unknown');
    }
}
