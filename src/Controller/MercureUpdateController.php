<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Routing\Attribute\Route;

class MercureUpdateController extends AbstractController
{
    #[Route('/api/mercure/publish', name: 'mercure_publish', methods: ['POST'])]
    public function publish(Request $request, HubInterface $hub): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!is_array($data)) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        if (!isset($data['topic']) || !is_string($data['topic'])) {
            return new JsonResponse(['error' => 'Topic is required and must be a string'], 400);
        }

        $topic = $data['topic'];
        $message = is_array($data['message'] ?? null) ? $data['message'] : [];

        $payload = json_encode(['timestamp' => date('c'), ...$message]);
        if ($payload === false) {
            return new JsonResponse(['error' => 'Failed to encode message'], 400);
        }

        $update = new Update(
            topics: $topic,
            data: $payload,
        );

        $hub->publish($update);

        return new JsonResponse(['success' => true, 'topic' => $topic]);
    }

    #[Route('/api/mercure/transactions', name: 'mercure_transactions', methods: ['POST'])]
    public function publishTransaction(Request $request, HubInterface $hub): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        $payload = json_encode([
            'action' => 'new_transaction',
            'transaction' => $data,
            'timestamp' => date('c'),
        ]);
        if ($payload === false) {
            return new JsonResponse(['error' => 'Failed to encode transaction'], 400);
        }

        $update = new Update(
            topics: 'admin/transactions',
            data: $payload,
        );

        $hub->publish($update);

        return new JsonResponse(['success' => true, 'message' => 'Transaction published']);
    }

}
