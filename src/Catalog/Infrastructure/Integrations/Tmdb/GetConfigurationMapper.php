<?php

declare(strict_types=1);

namespace App\Catalog\Infrastructure\Integrations\Tmdb;

use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class GetConfigurationMapper extends AbstractMapper
{
    public static function getAction(): string
    {
        return GetConfigurationAction::class;
    }

    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        /** @var array{images: array<string, mixed>} $response */
        return new GetConfigurationResponse($response['images']);
    }
}
