<?php

declare(strict_types=1);

namespace App\Tour\Domain;

interface TourRegistry
{
    /**
     * @return list<TourStep>
     */
    public function getAllSteps(): array;

    public function getStep(string $stepId): TourStep;

    public function getPreviousStep(string $stepId): ?TourStep;

    public function getNextStep(string $stepId): ?TourStep;
}
