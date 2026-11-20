<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Integration;

/**
 * An external API answered successfully but broke its own contract:
 * a required field is missing, renamed or holds a value of the wrong type.
 */
final class UnexpectedApiResponse extends \UnexpectedValueException
{
    public static function invalidField(string $source, string $field, string $expected): self
    {
        return new self(\sprintf('%s: field "%s" is missing or not %s.', $source, $field, $expected));
    }

    public static function unreadable(string $source, \Throwable $previous): self
    {
        return new self(\sprintf('%s: %s', $source, $previous->getMessage()), 0, $previous);
    }
}
