<?php

declare(strict_types=1);

namespace App\Shared\Observability\Lifecycle;

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

        if ($statusCode >= 200 && $statusCode < 300) {
            return '2xx';
        }

        if ($statusCode >= 300 && $statusCode < 400) {
            return '3xx';
        }

        if ($statusCode >= 400 && $statusCode < 500) {
            return '4xx';
        }

        if ($statusCode >= 500 && $statusCode < 600) {
            return '5xx';
        }

        return 'other';
    }
}
