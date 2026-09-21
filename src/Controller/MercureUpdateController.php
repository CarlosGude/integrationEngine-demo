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
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['error' => 'Invalid JSON'], 400);
        }

        $validation = $this->validateWebhookPayload($payload);
        if ($validation !== null) {
            return $validation;
        }

        $this->publishPaymentSucceeded($payload, $hub);

        return new JsonResponse(['success' => true]);
    }

    /**
     * @param array<mixed> $payload
     */
    private function validateWebhookPayload(array $payload): ?JsonResponse
    {
        $eventType = $payload['type'] ?? null;
        if (!is_string($eventType)) {
            return new JsonResponse(['error' => 'Event type is required'], 400);
        }

        if ($eventType === 'payment_intent.succeeded') {
            $data = $payload['data'] ?? [];
            if (!is_array($data) || !isset($data['object']) || !is_array($data['object'])) {
                return new JsonResponse(['error' => 'Invalid payload structure'], 400);
            }
        }

        return null;
    }

    /**
     * @param array<mixed> $payload
     */
    private function publishPaymentSucceeded(array $payload, HubInterface $hub): void
    {
        $eventType = $payload['type'] ?? null;
        if ($eventType === 'payment_intent.succeeded') {
            $data = $payload['data'] ?? [];
            if (is_array($data) && isset($data['object']) && is_array($data['object'])) {
                $object = $data['object'];
                $update = new Update(
                    topics: 'admin/payments',
                    data: (string) json_encode([
                        'action' => 'payment_succeeded',
                        'paymentIntentId' => $object['id'] ?? null,
                        'amount' => $object['amount'] ?? null,
                        'currency' => $object['currency'] ?? 'USD',
                        'status' => 'succeeded',
                        'timestamp' => date('c'),
                    ]),
                );

                $hub->publish($update);
            }
        }
    }
}
