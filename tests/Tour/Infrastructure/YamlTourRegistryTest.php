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

    public function testMissingTitleDescriptionAndOrderFallBackToDefaults(): void
    {
        $file = sys_get_temp_dir().'/tour-registry-'.uniqid().'.yaml';
        file_put_contents($file, <<<'YAML'
            steps:
              bare:
                foo: irrelevant
            YAML);

        try {
            $registry = new YamlTourRegistry($file);
            $step = $registry->getStep('bare');

            self::assertSame('bare', $step->id);
            self::assertSame('', $step->title);
            self::assertSame('', $step->description);
            self::assertSame(0, $step->order);
        } finally {
            @unlink($file);
        }
    }

    public function testWrongTypedTitleDescriptionAndOrderFallBackToDefaults(): void
    {
        $file = sys_get_temp_dir().'/tour-registry-'.uniqid().'.yaml';
        file_put_contents($file, <<<'YAML'
            steps:
              wrongTypes:
                title: 123
                description: true
                order: "not-a-number"
            YAML);

        try {
            $registry = new YamlTourRegistry($file);
            $step = $registry->getStep('wrongTypes');

            self::assertSame('', $step->title);
            self::assertSame('', $step->description);
            self::assertSame(0, $step->order);
        } finally {
            @unlink($file);
        }
    }

    public function testNumericYamlKeyIsCastToAStringId(): void
    {
        $file = sys_get_temp_dir().'/tour-registry-'.uniqid().'.yaml';
        // An unquoted numeric YAML key parses as a PHP int, not a string.
        file_put_contents($file, <<<'YAML'
            steps:
              100:
                title: Numeric key
                order: 1
            YAML);

        try {
            $registry = new YamlTourRegistry($file);
            $step = $registry->getStep('100');

            self::assertSame('100', $step->id);
            self::assertIsString($step->id);
        } finally {
            @unlink($file);
        }
    }
}
