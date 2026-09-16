<?php

declare(strict_types=1);

namespace Tests\Tour\Infrastructure;

use App\Tour\Domain\SnippetNotFoundException;
use App\Tour\Infrastructure\SourceSnippetExtractor;
use PHPUnit\Framework\TestCase;

final class SourceSnippetExtractorTest extends TestCase
{
    private SourceSnippetExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new SourceSnippetExtractor(
            projectDir: __DIR__.'/../../..',
            allowedDirs: ['src', 'tests'],
        );
    }

    public function testExtractValidSnippet(): void
    {
        $snippet = $this->extractor->extract('catalog/movies', 'src/Catalog/Domain/Movie.php');

        self::assertNotEmpty($snippet);
        self::assertStringContainsString('Movie', $snippet);
    }

    public function testExtractThrowsForMissingMarkers(): void
    {
        self::expectException(SnippetNotFoundException::class);
        $this->extractor->extract('nonexistent', 'src/Catalog/Domain/Movie.php');
    }

    public function testExtractThrowsForPathTraversal(): void
    {
        self::expectException(SnippetNotFoundException::class);
        $this->extractor->extract('test', '../../.env');
    }

    public function testExtractThrowsForDisallowedDirectory(): void
    {
        self::expectException(SnippetNotFoundException::class);
        $this->extractor->extract('test', 'var/cache/file.php');
    }

    public function testExtractThrowsForNonexistentFile(): void
    {
        self::expectException(SnippetNotFoundException::class);
        $this->extractor->extract('test', 'src/NonExistent/File.php');
    }
}
