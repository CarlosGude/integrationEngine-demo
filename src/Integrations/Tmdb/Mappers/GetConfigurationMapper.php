<?php

declare(strict_types=1);

namespace App\Integrations\Tmdb\Mappers;

use App\Integrations\Tmdb\GetConfiguration\GetConfigurationAction;
use App\Integrations\Tmdb\GetConfiguration\GetConfigurationResponse;
use App\Shared\Infrastructure\Integration\ResponseFields;
use IntegrationEngine\Core\Contract\Action\AbstractAction;
use IntegrationEngine\Core\Contract\Mapper\AbstractMapper;
use IntegrationEngine\Core\Contract\Response\ResponseInterface;

final class GetConfigurationMapper extends AbstractMapper
{
    private const SOURCE = 'TMDB get_configuration';

    public static function getAction(): string
    {
        return GetConfigurationAction::class;
    }

    protected static function transform(AbstractAction $action, array $response, array $headers): ResponseInterface
    {
        $images = ResponseFields::array($response, 'images', self::SOURCE);

        return new GetConfigurationResponse(
            posterSecureBaseUrl: ResponseFields::string($images, 'secure_base_url', self::SOURCE.' images'),
            posterSizes: ResponseFields::stringList($images, 'poster_sizes', self::SOURCE.' images'),
        );
    }
}
