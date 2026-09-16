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
        public ?string $posterUrl = null,
    ) {
    }
}
// tour:end
