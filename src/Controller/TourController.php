<?php

declare(strict_types=1);

namespace App\Controller;

use App\Shared\Observability\TraceRecorderMiddleware;
use App\Tour\Application\TourStepRunner;
use App\Tour\Domain\StepNotFoundException;
use App\Tour\Domain\TourRegistry;
use App\Tour\Domain\TourStep;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class TourController extends AbstractController
{
    public function __construct(
        private readonly TourRegistry $tourRegistry,
        private readonly TourStepRunner $runner,
    ) {
    }

    #[Route('/{_locale}/tour/{stepId}', name: 'tour_step', requirements: ['_locale' => 'en|es'])]
    public function step(string $stepId): Response
    {
        $step = $this->findStep($stepId);

        return $this->render('tour/step.html.twig', [
            'step' => $step,
            'previousStep' => $this->tourRegistry->getPreviousStep($stepId),
            'nextStep' => $this->tourRegistry->getNextStep($stepId),
        ]);
    }

    #[Route('/{_locale}/tour/{stepId}/run', name: 'tour_run', methods: ['POST'], requirements: ['_locale' => 'en|es'])]
    public function run(string $stepId): JsonResponse
    {
        $this->findStep($stepId);

        TraceRecorderMiddleware::startTrace();
        try {
            try {
                $result = ['success' => true, 'data' => $this->runner->run($stepId)];
            } catch (\Throwable $e) {
                $result = ['success' => false, 'error' => $e->getMessage()];
            }
        } finally {
            $trace = TraceRecorderMiddleware::getCurrentTrace();
            TraceRecorderMiddleware::clearTrace();
        }

        return $this->json([
            'result' => $result,
            'trace' => $trace?->toArray() ?? ['calls' => [], 'total_duration_ms' => 0.0],
        ]);
    }

    private function findStep(string $stepId): TourStep
    {
        try {
            return $this->tourRegistry->getStep($stepId);
        } catch (StepNotFoundException $e) {
            throw $this->createNotFoundException($e->getMessage(), $e);
        }
    }
}
