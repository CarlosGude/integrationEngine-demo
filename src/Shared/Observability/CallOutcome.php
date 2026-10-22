<?php

declare(strict_types=1);

namespace App\Shared\Observability;

/**
 * How a traced call ended. MappingFailed is the one only ResponseReceived
 * makes visible: the API answered, then the mapper threw.
 */
enum CallOutcome: string
{
    case Mapped = 'mapped';
    case MappingFailed = 'mapping_failed';
    case Failed = 'failed';
}
