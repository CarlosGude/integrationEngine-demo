<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Integration;

/**
 * Reads required fields from a decoded API response, rejecting any field that is
 * missing or of the wrong type instead of replacing it with a default value.
 * $source names the response in the error message, e.g. "TMDB get_movie".
 */
final class ResponseFields
{
    /** @param array<mixed> $data */
    public static function int(array $data, string $field, string $source): int
    {
        $value = $data[$field] ?? null;

        return \is_int($value) ? $value : throw UnexpectedApiResponse::invalidField($source, $field, 'an integer');
    }

    /** @param array<mixed> $data */
    public static function number(array $data, string $field, string $source): float
    {
        $value = $data[$field] ?? null;

        return \is_int($value) || \is_float($value) ? (float) $value : throw UnexpectedApiResponse::invalidField($source, $field, 'a number');
    }

    /** @param array<mixed> $data */
    public static function string(array $data, string $field, string $source): string
    {
        $value = $data[$field] ?? null;

        return \is_string($value) ? $value : throw UnexpectedApiResponse::invalidField($source, $field, 'a string');
    }

    /**
     * For fields the API always sends but documents as nullable: the key must be
     * present, so a renamed field is still rejected.
     *
     * @param array<mixed> $data
     */
    public static function stringOrNull(array $data, string $field, string $source): ?string
    {
        if (!\array_key_exists($field, $data)) {
            throw UnexpectedApiResponse::invalidField($source, $field, 'a string or null');
        }

        $value = $data[$field];

        return $value === null || \is_string($value) ? $value : throw UnexpectedApiResponse::invalidField($source, $field, 'a string or null');
    }

    /**
     * @param array<mixed> $data
     *
     * @return array<mixed>
     */
    public static function array(array $data, string $field, string $source): array
    {
        $value = $data[$field] ?? null;

        return \is_array($value) ? $value : throw UnexpectedApiResponse::invalidField($source, $field, 'an object or array');
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<string>
     */
    public static function stringList(array $data, string $field, string $source): array
    {
        $value = $data[$field] ?? null;

        if (!\is_array($value) || !array_is_list($value)) {
            throw UnexpectedApiResponse::invalidField($source, $field, 'a list of strings');
        }

        foreach ($value as $item) {
            if (!\is_string($item)) {
                throw UnexpectedApiResponse::invalidField($source, $field, 'a list of strings');
            }
        }

        /** @var list<string> $value */
        return $value;
    }
}
