<?php

declare(strict_types=1);

namespace App\Pricing\Infrastructure\Integrations\Countries;

use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class GetCountriesResponse implements ResponseInterface
{
    /**
     * @param array<int, array{code: string, name: string, continent: string}> $countries
     */
    public function __construct(
        private readonly array $countries,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['countries' => $this->countries];
    }

    /**
     * @return array<int, array{code: string, name: string, continent: string}>
     */
    public function countries(): array
    {
        return $this->countries;
    }
}
