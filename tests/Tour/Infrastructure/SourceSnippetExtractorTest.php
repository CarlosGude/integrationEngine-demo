<?php

declare(strict_types=1);

namespace Tests\Tour\Infrastructure;

use App\Tour\Domain\SnippetNotFoundException;
use App\Tour\Infrastructure\SourceSnippetExtractor;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SourceSnippetExtractorTest extends TestCase
{
    private SourceSnippetExtractor $extractor;
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->extractor = new SourceSnippetExtractor(
            projectDir: __DIR__.'/../../..',
            allowedDirs: ['src', 'tests'],
        );

        $this->tmpDir = sys_get_temp_dir().'/snippet-extractor-'.uniqid();
        mkdir($this->tmpDir.'/allowed', 0o777, true);
        file_put_contents($this->tmpDir.'/allowed/fixture.php', <<<'PHP'
            <?php
            // tour:start example/first
            $first = 'one';
            // tour:end
            // tour:start example/second
            $second = 'two';
            // tour:end
            PHP);
    }

    protected function tearDown(): void
    {
        @unlink($this->tmpDir.'/allowed/fixture.php');
        @rmdir($this->tmpDir.'/allowed');
        @rmdir($this->tmpDir);
    }

    #[Test]
    public function extractsExactlyTheContentBetweenTheMarkers(): void
    {
        $snippet = $this->extractor->extract('catalog/movies', 'src/Catalog/Domain/Movie.php');

        self::assertNotEmpty($snippet);
        self::assertStringContainsString('Movie', $snippet);
        self::assertStringStartsWith('final readonly class Movie', $snippet);
        // Documents a real quirk: the closing "// tour:end" marker's "// "
        // prefix ends up inside the extracted snippet, not just the source.
        self::assertStringEndsWith("}\n//", $snippet);
    }

    #[Test]
    public function extractsOnlyItsOwnSnippetNotAnAdjacentOne(): void
    {
        $extractor = new SourceSnippetExtractor(projectDir: $this->tmpDir, allowedDirs: ['allowed']);

        $first = $extractor->extract('example/first', 'allowed/fixture.php');
        $second = $extractor->extract('example/second', 'allowed/fixture.php');

        // extractFromContent() locates "tour:end" by its bare text, so the
        // "// " comment prefix in front of it is swept into the snippet as
        // a trailing "//". Real snippets have the same artifact — see the
        // extraction of "catalog/movies" from Movie.php below.
        self::assertSame("\$first = 'one';\n//", $first);
        self::assertSame("\$second = 'two';\n//", $second);
        self::assertStringNotContainsString('second', $first);
        self::assertStringNotContainsString('first', $second);
    }

    #[Test]
    public function extractThrowsForMissingMarkers(): void
    {
        $this->expectException(SnippetNotFoundException::class);
        $this->expectExceptionMessage('Snippet "nonexistent" not found in marked code blocks');
        $this->extractor->extract('nonexistent', 'src/Catalog/Domain/Movie.php');
    }

    #[Test]
    public function extractThrowsForAnUnterminatedMarker(): void
    {
        file_put_contents($this->tmpDir.'/allowed/unterminated.php', "<?php\n// tour:start example/unterminated\n\$x = 1;\n");
        $extractor = new SourceSnippetExtractor(projectDir: $this->tmpDir, allowedDirs: ['allowed']);

        try {
            $this->expectException(SnippetNotFoundException::class);
            $this->expectExceptionMessage('Snippet "example/unterminated" not found in marked code blocks');
            $extractor->extract('example/unterminated', 'allowed/unterminated.php');
        } finally {
            @unlink($this->tmpDir.'/allowed/unterminated.php');
        }
    }

    #[Test]
    public function extractThrowsForPathTraversal(): void
    {
        $this->expectException(SnippetNotFoundException::class);
        $this->expectExceptionMessage('Cannot extract snippet from file "../../.env" (traversal)');
        $this->extractor->extract('test', '../../.env');
    }

    #[Test]
    public function extractThrowsForDisallowedDirectory(): void
    {
        $this->expectException(SnippetNotFoundException::class);
        $this->expectExceptionMessage('Cannot extract snippet from file "var/cache/file.php" (unauthorized)');
        $this->extractor->extract('test', 'var/cache/file.php');
    }

    #[Test]
    public function directoryPrefixMustBeFollowedByASlash(): void
    {
        // "srcish/x.php" starts with "src" but is not inside the "src"
        // directory: the check must require the trailing slash.
        $this->expectException(SnippetNotFoundException::class);
        $this->expectExceptionMessage('unauthorized');
        $this->extractor->extract('test', 'srcish/x.php');
    }

    #[Test]
    public function checksEveryAllowedDirectoryNotJustTheFirst(): void
    {
        $extractor = new SourceSnippetExtractor(
            projectDir: __DIR__.'/../../..',
            allowedDirs: ['does-not-exist', 'src'],
        );

        $snippet = $extractor->extract('catalog/movies', 'src/Catalog/Domain/Movie.php');

        self::assertNotEmpty($snippet);
    }

    #[Test]
    public function extractThrowsForNonexistentFile(): void
    {
        $this->expectException(SnippetNotFoundException::class);
        $this->expectExceptionMessage('Cannot extract snippet from file "src/NonExistent/File.php" (test)');
        $this->extractor->extract('test', 'src/NonExistent/File.php');
    }
}
