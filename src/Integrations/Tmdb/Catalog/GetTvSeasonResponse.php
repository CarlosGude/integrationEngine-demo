<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb\Catalog;

use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class GetTvSeasonResponse implements ResponseInterface
{
    /** @param array<string, mixed> $episodes */
    public function __construct(
        private readonly int $id,
        private readonly string $name,
        private readonly array $episodes,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'episodes' => $this->episodes,
        ];
    }

    public function id(): int
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    /** @return array<string, mixed> */
    public function episodes(): array
    {
        return $this->episodes;
    }
}
