<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb\Catalog;

use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class GetMovieResponse implements ResponseInterface
{
    public function __construct(
        private readonly int $id,
        private readonly string $title,
        private readonly string $overview,
        private readonly string $posterPath,
        private readonly float $voteAverage,
        private readonly string $releaseDate,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'overview' => $this->overview,
            'poster_path' => $this->posterPath,
            'vote_average' => $this->voteAverage,
            'release_date' => $this->releaseDate,
        ];
    }

    public function id(): int
    {
        return $this->id;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function overview(): string
    {
        return $this->overview;
    }

    public function posterPath(): string
    {
        return $this->posterPath;
    }

    public function voteAverage(): float
    {
        return $this->voteAverage;
    }

    public function releaseDate(): string
    {
        return $this->releaseDate;
    }
}
