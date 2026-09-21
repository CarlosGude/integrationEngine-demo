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
        $data = json_decode($request->getContent(), true) ?? [];

        $topic = (string) ($data['topic'] ?? 'admin/updates');
        $message = (array) ($data['message'] ?? []);

        // Publish update to Mercure
        $update = new Update(
            topics: $topic,
            data: json_encode(['timestamp' => date('c'), ...$message]),
        );

        $hub->publish($update);

        return new JsonResponse(['success' => true, 'topic' => $topic]);
    }

    #[Route('/api/mercure/transactions', name: 'mercure_transactions', methods: ['POST'])]
    public function publishTransaction(Request $request, HubInterface $hub): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];

        // Publish new transaction update
        $update = new Update(
            topics: 'admin/transactions',
            data: (string) json_encode([
                'action' => 'new_transaction',
                'transaction' => $data,
                'timestamp' => date('c'),
            ]),
        );

        $hub->publish($update);

        return new JsonResponse(['success' => true, 'message' => 'Transaction published']);
    }

    #[Route('/api/mercure/webhook', name: 'mercure_webhook', methods: ['POST'])]
    public function handleStripeWebhook(Request $request, HubInterface $hub): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $eventType = (string) ($payload['type'] ?? '');

        if ($eventType === 'payment_intent.succeeded') {
            $update = new Update(
                topics: 'admin/payments',
                data: (string) json_encode([
                    'action' => 'payment_succeeded',
                    'paymentIntentId' => $payload['data']['object']['id'] ?? null,
                    'amount' => $payload['data']['object']['amount'] ?? null,
                    'currency' => $payload['data']['object']['currency'] ?? 'USD',
                    'status' => 'succeeded',
                    'timestamp' => date('c'),
                ]),
            );

            $hub->publish($update);
        }

        return new JsonResponse(['success' => true]);
    }
}
