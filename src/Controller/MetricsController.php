<?php

declare(strict_types=1);

namespace App\Controller;

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

final class MetricsController extends AbstractController
{
    public function __construct(
        private readonly CollectorRegistry $registry,
        private readonly bool $enabled,
    ) {
    }

    #[Route('/metrics', name: 'metrics', methods: ['GET'])]
    public function __invoke(): Response
    {
        if (!$this->enabled) {
            throw $this->createNotFoundException();
        }

        $renderer = new RenderTextFormat();

        return new Response(
            $renderer->render($this->registry->getMetricFamilySamples()),
            Response::HTTP_OK,
            ['Content-Type' => RenderTextFormat::MIME_TYPE],
        );
    }
}
