<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Integrations\Tmdb;

use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class GetConfigurationResponse implements ResponseInterface
{
    /** @param array<string, mixed> $images */
    public function __construct(
        private readonly array $images,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['images' => $this->images];
    }

    /** @return array<string, mixed> */
    public function images(): array
    {
        return $this->images;
    }

    public function posterSecureBaseUrl(): string
    {
        return $this->images['secure_base_url'] ?? '';
    }

    public function posterSizes(): array
    {
        return $this->images['poster_sizes'] ?? [];
    }
}
