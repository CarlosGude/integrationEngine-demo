<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb\GetConfiguration;

use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final readonly class GetConfigurationResponse implements ResponseInterface
{
    /** @param list<string> $posterSizes */
    public function __construct(
        private string $posterSecureBaseUrl,
        private array $posterSizes,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['images' => ['secure_base_url' => $this->posterSecureBaseUrl, 'poster_sizes' => $this->posterSizes]];
    }

    public function posterSecureBaseUrl(): string
    {
        return $this->posterSecureBaseUrl;
    }

    /** @return list<string> */
    public function posterSizes(): array
    {
        return $this->posterSizes;
    }
}
