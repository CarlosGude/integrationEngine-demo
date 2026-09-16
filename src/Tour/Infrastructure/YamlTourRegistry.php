<?php

declare(strict_types=1);

namespace App\Tour\Infrastructure;

use App\Tour\Domain\StepNotFoundException;
use App\Tour\Domain\TourRegistry;
use App\Tour\Domain\TourStep;
use Symfony\Component\Yaml\Yaml;

final class YamlTourRegistry implements TourRegistry
{
    /**
     * @var list<TourStep>
     */
    private array $steps;

    public function __construct(string $configPath)
    {
        /** @var array<string, mixed> $config */
        $config = Yaml::parseFile($configPath);
        /** @var array<array-key, mixed> $stepsData */
        $stepsData = $config['steps'] ?? [];
        $this->steps = $this->buildSteps($stepsData);
        usort($this->steps, static fn (TourStep $a, TourStep $b) => $a->order <=> $b->order);
    }

    public function getAllSteps(): array
    {
        return $this->steps;
    }

    public function getStep(string $stepId): TourStep
    {
        foreach ($this->steps as $step) {
            if ($step->id === $stepId) {
                return $step;
            }
        }

        throw StepNotFoundException::forId($stepId);
    }

    public function getPreviousStep(string $stepId): ?TourStep
    {
        $currentIndex = $this->findStepIndex($stepId);
        return $currentIndex > 0 ? $this->steps[$currentIndex - 1] : null;
    }

    public function getNextStep(string $stepId): ?TourStep
    {
        $currentIndex = $this->findStepIndex($stepId);
        $nextIndex = $currentIndex + 1;
        return $nextIndex < count($this->steps) ? $this->steps[$nextIndex] : null;
    }

    /**
     * @param array<array-key, mixed> $stepsConfig
     *
     * @return list<TourStep>
     */
    private function buildSteps(array $stepsConfig): array
    {
        $steps = [];
        foreach ($stepsConfig as $stepId => $stepData) {
            /** @var array<string, mixed>|null $stepDataArray */
            $stepDataArray = is_array($stepData) ? $stepData : null;

            $steps[] = new TourStep(
                id: (string) $stepId,
                title: is_string($stepDataArray['title'] ?? null) ? $stepDataArray['title'] : '',
                description: is_string($stepDataArray['description'] ?? null) ? $stepDataArray['description'] : '',
                order: is_int($stepDataArray['order'] ?? null) ? $stepDataArray['order'] : 0,
            );
        }

        return $steps;
    }

    private function findStepIndex(string $stepId): int
    {
        foreach ($this->steps as $index => $step) {
            if ($step->id === $stepId) {
                return $index;
            }
        }

        throw StepNotFoundException::forId($stepId);
    }
}
