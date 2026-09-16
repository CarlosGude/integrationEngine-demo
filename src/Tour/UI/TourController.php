<?php

declare(strict_types=1);

namespace App\Tour\UI;

use App\Shared\Observability\TraceRecorderMiddleware;
use App\Tour\Domain\TourRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class TourController extends AbstractController
{
    public function __construct(
        private readonly TourRegistry $tourRegistry,
    ) {
    }

    #[Route('/{_locale}/tour/{stepId}', name: 'tour_step', requirements: ['_locale' => 'en|es'])]
    public function step(string $stepId): Response
    {
        $step = $this->tourRegistry->getStep($stepId);

        return $this->render('tour/step.html.twig', [
            'step' => $step,
            'previousStep' => $this->tourRegistry->getPreviousStep($stepId),
            'nextStep' => $this->tourRegistry->getNextStep($stepId),
        ]);
    }

    #[Route('/{_locale}/tour/{stepId}/run', name: 'tour_run', methods: ['POST'], requirements: ['_locale' => 'en|es'])]
    public function run(string $stepId): JsonResponse
    {
        $step = $this->tourRegistry->getStep($stepId);

        TraceRecorderMiddleware::startTrace();
        try {
            // This is a placeholder - actual step execution would happen here
            $result = ['success' => true, 'message' => 'Step executed'];
        } finally {
            $trace = TraceRecorderMiddleware::getCurrentTrace();
            TraceRecorderMiddleware::clearTrace();
        }

        return $this->json([
            'result' => $result,
            'trace' => $trace?->toArray() ?? ['calls' => [], 'total_duration_ms' => 0.0],
        ]);
    }
}
