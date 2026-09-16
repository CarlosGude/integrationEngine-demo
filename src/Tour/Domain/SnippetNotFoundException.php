<?php

declare(strict_types=1);

namespace App\Tour\Domain;

use RuntimeException;

final class SnippetNotFoundException extends \RuntimeException
{
    public static function forId(string $snippetId): self
    {
        return new self(sprintf('Snippet "%s" not found in marked code blocks', $snippetId));
    }

    public static function forFile(string $filePath, string $context): self
    {
        return new self(sprintf('Cannot extract snippet from file "%s" (%s)', $filePath, $context));
    }
}
