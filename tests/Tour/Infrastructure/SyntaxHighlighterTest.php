<?php

declare(strict_types=1);

namespace Tests\Tour\Infrastructure;

use App\Tour\Infrastructure\SyntaxHighlighter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Characterizes SyntaxHighlighter's actual output, including a real quirk:
 * each highlighting pass writes raw `class="..."` markup into the string,
 * and the string-literal pass that runs last re-matches that markup's own
 * quotes, nesting spans inside each other. Real source-code string literals
 * never get wrapped at all, because htmlspecialchars() already turned their
 * quote characters into entities before the string-literal regex runs. This
 * suite pins down the current behavior; it does not assert it is correct.
 */
final class SyntaxHighlighterTest extends TestCase
{
    private SyntaxHighlighter $highlighter;

    protected function setUp(): void
    {
        $this->highlighter = new SyntaxHighlighter();
    }

    #[Test]
    public function nonPhpLanguageIsOnlyHtmlEscaped(): void
    {
        $result = $this->highlighter->highlight('<b>bold</b> & "quoted"', 'yaml');

        self::assertSame('&lt;b&gt;bold&lt;/b&gt; &amp; &quot;quoted&quot;', $result);
    }

    #[Test]
    public function phpIsTheDefaultLanguage(): void
    {
        $result = $this->highlighter->highlight('<?php echo 1;');

        self::assertSame('&lt;?php <span class=<span class="string">"keyword"</span>>echo</span> 1;', $result);
    }

    #[Test]
    public function phpKeywordsAreWrappedInSpans(): void
    {
        $result = $this->highlighter->highlight('public function foo(): void {}', 'php');

        self::assertSame(
            '<span class=<span class="string">"keyword"</span>>public</span> <span class=<span class="string">"keyword"</span>>function</span> foo(): void {}',
            $result,
        );
    }

    #[Test]
    public function voidIsNotInTheKeywordList(): void
    {
        // The keyword list predates several modern return-type keywords
        // (void, int, string, bool, self, ...); "void" passes through
        // untouched.
        $result = $this->highlighter->highlight('void', 'php');

        self::assertSame('void', $result);
    }

    #[Test]
    public function keywordMatchingRespectsWordBoundaries(): void
    {
        // "class" must not match inside "classroom": only the $classroom
        // variable gets wrapped, "class" itself does not.
        $result = $this->highlighter->highlight('$classroom = 1;', 'php');

        self::assertSame('<span class=<span class="string">"variable"</span>>$classroom</span> = 1;', $result);
    }

    #[Test]
    public function variablesAreWrappedInSpans(): void
    {
        $result = $this->highlighter->highlight('$movieId = 550;', 'php');

        self::assertSame('<span class=<span class="string">"variable"</span>>$movieId</span> = 550;', $result);
    }

    #[Test]
    public function realStringLiteralsAreNeverWrappedBecauseTheirQuotesAreAlreadyEscaped(): void
    {
        $result = $this->highlighter->highlight("\$a = 'hello'; \$b = \"world\";", 'php');

        self::assertSame(
            '<span class=<span class="string">"variable"</span>>$a</span> = &#039;hello&#039;; '
            .'<span class=<span class="string">"variable"</span>>$b</span> = &quot;world&quot;;',
            $result,
        );
        self::assertStringNotContainsString('hello</span>', $result);
    }

    #[Test]
    public function lineCommentsAreWrappedInSpans(): void
    {
        $result = $this->highlighter->highlight("echo 1; // a comment\necho 2;", 'php');

        self::assertSame(
            "<span class=<span class=\"string\">\"keyword\"</span>>echo</span> 1; <span class=\"comment\">// a comment</span>\n"
            .'<span class=<span class="string">"keyword"</span>>echo</span> 2;',
            $result,
        );
    }

    #[Test]
    public function blockCommentsAreWrappedInSpans(): void
    {
        $result = $this->highlighter->highlight('/* a block comment */ echo 1;', 'php');

        self::assertSame(
            '<span class="comment">/* a block comment */</span> <span class=<span class="string">"keyword"</span>>echo</span> 1;',
            $result,
        );
    }

    #[Test]
    public function htmlSpecialCharactersInPhpCodeAreEscapedBeforeHighlighting(): void
    {
        $result = $this->highlighter->highlight('$a = "<script>";', 'php');

        self::assertSame('<span class=<span class="string">"variable"</span>>$a</span> = &quot;&lt;script&gt;&quot;;', $result);
        self::assertStringNotContainsString('<script>', $result);
    }
}
