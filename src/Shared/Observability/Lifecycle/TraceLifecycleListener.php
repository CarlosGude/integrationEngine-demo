<?php

declare(strict_types=1);

namespace App\Shared\Observability\Lifecycle;

use App\Shared\Observability\CallOutcome;
use App\Shared\Observability\TraceRecorderMiddleware;
use IntegrationEngine\Core\Event\RequestFailed;
use IntegrationEngine\Core\Event\RequestSent;
use IntegrationEngine\Core\Event\ResponseMapped;
use IntegrationEngine\Core\Event\ResponseReceived;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Feeds the tour's execution trace from the engine's lifecycle events: one
 * entry per integration call — one per item in a sendMany() batch — with the
 * real status code and, once the API has answered, upstream time and mapping
 * time split apart.
 *
 * Outside a tour run, TraceRecorderMiddleware::recordCall() is a no-op.
 */
final class TraceLifecycleListener
{
    /** @var array<string, string> */
    private array $methods = [];

    private readonly ReceivedResponses $received;

    public function __construct()
    {
        $this->received = new ReceivedResponses();
    }

    #[AsEventListener(event: RequestSent::class)]
    public function onRequestSent(RequestSent $event): void
    {
        $this->methods[ReceivedResponses::key($event->integrationName, $event->action, $event->requestKey)] = $event->method;
    }

    #[AsEventListener(event: ResponseReceived::class)]
    public function onResponseReceived(ResponseReceived $event): void
    {
        $this->received->remember($event);
    }

    #[AsEventListener(event: ResponseMapped::class)]
    public function onResponseMapped(ResponseMapped $event): void
    {
        $received = $this->received->take($event->integrationName, $event->action, $event->requestKey);

        TraceRecorderMiddleware::recordCall(
            action: $event->integrationName.'.'.$event->action,
            method: $this->takeMethod($event->integrationName, $event->action, $event->requestKey),
            status: $event->statusCode,
            outcome: CallOutcome::Mapped,
            durationMs: $event->durationMs,
            upstreamMs: $received?->durationMs,
            mappingMs: $received === null ? null : ReceivedResponses::mappingMs($received, $event->timestamp),
        );
    }

    #[AsEventListener(event: RequestFailed::class)]
    public function onRequestFailed(RequestFailed $event): void
    {
        $received = $this->received->take($event->integrationName, $event->action, $event->requestKey);

        TraceRecorderMiddleware::recordCall(
            action: $event->integrationName.'.'.$event->action,
            method: $this->takeMethod($event->integrationName, $event->action, $event->requestKey),
            // A mapping failure is reported with status 0 (it isn't an
            // upstream error); the status the API actually answered with
            // is the one on ResponseReceived.
            status: $received->statusCode ?? $event->statusCode,
            outcome: $received === null ? CallOutcome::Failed : CallOutcome::MappingFailed,
            durationMs: $event->durationMs,
            upstreamMs: $received?->durationMs,
        );
    }

    private function takeMethod(string $integrationName, string $action, int|string|null $requestKey): string
    {
        $key = ReceivedResponses::key($integrationName, $action, $requestKey);
        $method = $this->methods[$key] ?? '';
        unset($this->methods[$key]);

        return $method;
    }
}
