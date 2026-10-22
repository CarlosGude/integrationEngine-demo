<?php

declare(strict_types=1);

namespace App\Integrations\Countries\GetCountries;

use IntegrationEngine\Core\Contract\Action\GraphQLBodyInterface;

final readonly class GetCountriesBody implements GraphQLBodyInterface
{
    private function __construct()
    {
    }

    /** @param array<string, mixed> $data */
    public static function create(array $data): self
    {
        return new self();
    }

    public function getQuery(): string
    {
        return <<<'GRAPHQL'
{
  countries {
    code
    name
    continent {
      name
    }
  }
}
GRAPHQL;
    }

    /** @return array<string, mixed> */
    public function getVariables(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['query' => $this->getQuery(), 'variables' => $this->getVariables()];
    }
}
