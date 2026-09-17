<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Http;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Entity\PreparedRequest;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class CsvClientAdapter implements CsvClientAdapterInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function send(PreparedRequest $request): ResponseInterface
    {
        return $this->httpClient->request(
            $request->method(),
            $request->url(),
            ['headers' => $request->headers()],
        );
    }

    public function parseCSV(string $csvContent): array
    {
        $lines = \explode("\n", \trim($csvContent));
        if (empty($lines) || (count($lines) === 1 && empty($lines[0]))) {
            return [];
        }

        $header = \str_getcsv(\array_shift($lines));
        if (empty($header)) {
            throw new \InvalidArgumentException('CSV header is empty or missing');
        }

        $rows = [];

        foreach ($lines as $line) {
            if (empty(\trim($line))) {
                continue;
            }

            $values = \str_getcsv($line);
            if (\count($values) !== \count($header)) {
                throw new \InvalidArgumentException('CSV row has mismatched column count');
            }

            $rows[] = \array_combine($header, $values);
        }

        return $rows;
    }
}
