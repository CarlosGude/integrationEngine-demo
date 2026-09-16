<?php

declare(strict_types=1);

namespace App\Tour\Infrastructure;

use App\Tour\Domain\SnippetNotFoundException;

final class SourceSnippetExtractor
{
    /**
     * @param list<string> $allowedDirs
     */
    public function __construct(
        private readonly string $projectDir,
        private readonly array $allowedDirs,
    ) {
    }

    public function extract(string $snippetId, string $filePath): string
    {
        $this->validatePath($filePath);

        $fullPath = $this->projectDir.'/'.$filePath;

        if (!file_exists($fullPath)) {
            throw SnippetNotFoundException::forFile($filePath, $snippetId);
        }

        $content = file_get_contents($fullPath);
        if ($content === false) {
            throw SnippetNotFoundException::forFile($filePath, $snippetId);
        }

        return $this->extractFromContent($content, $snippetId, $filePath);
    }

    private function validatePath(string $filePath): void
    {
        if (str_contains($filePath, '..')) {
            throw SnippetNotFoundException::forFile($filePath, 'traversal');
        }

        $dirAllowed = false;
        foreach ($this->allowedDirs as $dir) {
            if (str_starts_with($filePath, $dir.'/')) {
                $dirAllowed = true;
                break;
            }
        }

        if (!$dirAllowed) {
            throw SnippetNotFoundException::forFile($filePath, 'unauthorized');
        }
    }

    private function extractFromContent(string $content, string $snippetId, string $filePath): string
    {
        $startMarker = "tour:start $snippetId";
        $endMarker = 'tour:end';

        $startPos = strpos($content, $startMarker);
        if ($startPos === false) {
            throw SnippetNotFoundException::forId($snippetId);
        }

        $startPos = strpos($content, "\n", $startPos);
        if ($startPos === false) {
            throw SnippetNotFoundException::forId($snippetId);
        }

        $endPos = strpos($content, $endMarker, $startPos);
        if ($endPos === false) {
            throw SnippetNotFoundException::forId($snippetId);
        }

        $snippet = substr($content, $startPos + 1, $endPos - $startPos - 1);

        return trim($snippet);
    }
}
