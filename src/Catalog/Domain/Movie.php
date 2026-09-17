<?php

declare(strict_types=1);

namespace App\Catalog\Domain;

// tour:start catalog/movies
final readonly class Movie
{
    public function __construct(
        public int $id,
        public string $title,
        public string $overview,
        public string $posterPath,
        public float $voteAverage,
        public string $releaseDate,
        public string $posterUrl,
    ) {
    }

    public static function fromInfrastructure(
        int $id,
        string $title,
        string $overview,
        string $posterPath,
        float $voteAverage,
        string $releaseDate,
        string $posterUrl,
    ): self {
        return new self($id, $title, $overview, $posterPath, $voteAverage, $releaseDate, $posterUrl);
    }
}
// tour:end
