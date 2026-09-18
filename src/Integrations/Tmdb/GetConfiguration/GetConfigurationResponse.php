<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb\GetConfiguration;

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
        $url = $this->images['secure_base_url'] ?? '';

        return \is_string($url) ? $url : '';
    }

    /** @return list<string> */
    public function posterSizes(): array
    {
        $sizes = $this->images['poster_sizes'] ?? [];

        return \is_array($sizes) ? array_values(array_filter($sizes, \is_string(...))) : [];
    }
}
