<?php

declare(strict_types=1);

namespace App\Shared\Observability;

/**
 * Buckets an HTTP status code into a low-cardinality label value.
 *
 * Used as the `status_class` label on metrics: the raw status code or any
 * per-request identifier would blow up label cardinality, so only the class
 * is ever exposed.
 */
final class StatusClass
{
    public static function fromCode(int $statusCode): string
    {
        if ($statusCode === 0) {
            return 'network';
        }

        return match (true) {
            $statusCode >= 200 && $statusCode < 300 => '2xx',
            $statusCode >= 300 && $statusCode < 400 => '3xx',
            $statusCode >= 400 && $statusCode < 500 => '4xx',
            $statusCode >= 500 && $statusCode < 600 => '5xx',
            default => 'other',
        };
    }
}
