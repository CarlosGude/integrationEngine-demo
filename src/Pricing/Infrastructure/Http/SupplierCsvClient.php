<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Http;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Action\ActionContextInterface;
use IntegrationEngine\Core\Contract\Client\ClientInterface;
use IntegrationEngine\Core\Contract\Client\RequestHeadersInterface;
use IntegrationEngine\Core\Exception\RequestResponseException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class SupplierCsvClient implements ClientInterface
{
    private const ENDPOINT = 'http://supplier/prices.csv';
    private const EXPECTED_PATH = '/prices.csv';

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {
    }

    public function send(
        AbstractAction $action,
        ?ActionContextInterface $context = null,
        ?RequestHeadersInterface $headers = null,
    ): array {
        $path = $action->getPath($context);

        if (self::EXPECTED_PATH !== $path) {
            throw new RequestResponseException(0, sprintf('Unsupported supplier path: %s', $path));
        }

        try {
            $response = $this->httpClient->request($action->getMethod(), self::ENDPOINT, [
                'headers' => $headers?->toArray() ?? [],
            ]);
            $statusCode = $response->getStatusCode();
            $content = $response->getContent(throw: false);

            if ($statusCode >= 400) {
                throw new RequestResponseException($statusCode, sprintf('%s %s returned HTTP %d', $action->getMethod(), $path, $statusCode));
            }

            return [
                'body' => ['csv' => $content],
                'headers' => $response->getHeaders(throw: false),
                'statusCode' => $statusCode,
            ];
        } catch (RequestResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new RequestResponseException(0, sprintf('Network error on %s %s: %s', $action->getMethod(), $path, $e->getMessage()));
        }
    }
}
