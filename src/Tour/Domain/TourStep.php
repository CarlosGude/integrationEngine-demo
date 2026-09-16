<?php

declare(strict_types=1);

namespace App\Tour\Domain;

final readonly class TourStep
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public int $order,
    ) {
    }
}
