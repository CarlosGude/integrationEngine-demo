<?php

declare(strict_types=1);

namespace Tests\Documentation;

use PHPUnit\Framework\TestCase;

/**
 * Guards T-15: documentation used to point at files that do not exist
 * (UserCrudController, AdminVoter, StripeFormClientAdapter, …), which made the
 * status tables unreliable.
 *
 * Every repository-relative path mentioned in the docs must either exist, or be
 * listed in ALLOWED_MISSING below — which means the surrounding prose already
 * says it does not exist yet. Adding an entry is a deliberate act, not a way to
 * make the test pass.
 */
final class DocumentedPathsExistTest extends TestCase
{
    private const ROOT = __DIR__.'/../..';

    /**
     * Paths the docs mention while stating plainly that they are not built.
     *
     * @var array<string, string> path => the document that frames it as planned
     */
    private const ALLOWED_MISSING = [
        // Recipes: "would live at …", "does not exist yet".
        'src/Command/ViewAuditLogCommand.php' => 'docs/ADMIN-FEATURES.md',
        'src/Security/AdminVoter.php' => 'docs/ADMIN-FEATURES.md',
        'src/Entity/Transaction.php' => 'docs/ADMIN-FEATURES.md',
        'src/EventListener/TransactionListener.php' => 'docs/MERCURE-WEBSOCKETS.md',
        'templates/bundles/EasyAdminBundle/css/custom.css' => 'docs/EASYADMIN.md',
        // Placeholder in a "build your own adapter" walkthrough.
        'src/YourIntegration/YourIntegration.yaml' => 'docs/CUSTOM-ADAPTERS.md',
        // Record of a migration already carried out; these were deleted.
        'src/Billing/Infrastructure/Http/StripeFormClientAdapter.php' => 'docs/PHASE3-INTEGRATION.md',
        'tests/Integrations/StripeFormClientAdapterTest.php' => 'docs/PHASE3-INTEGRATION.md',
    ];

    /**
     * TASKS.md is the backlog: it exists to describe things that are missing or
     * wrong, so its paths are deliberately not checked here.
     *
     * @var list<string>
     */
    private const SKIPPED_DOCUMENTS = ['TASKS.md'];

    public function testEveryPathMentionedInDocumentationExists(): void
    {
        $offenders = [];

        foreach ($this->documents() as $document) {
            $relative = substr($document, \strlen(realpath(self::ROOT) ?: '') + 1);

            if (\in_array($relative, self::SKIPPED_DOCUMENTS, true)) {
                continue;
            }

            foreach ($this->pathsMentionedIn($document) as $line => $path) {
                if (is_file(self::ROOT.'/'.$path) || is_dir(self::ROOT.'/'.$path)) {
                    continue;
                }

                if (\array_key_exists($path, self::ALLOWED_MISSING)) {
                    continue;
                }

                $offenders[] = "{$relative}:{$line} → {$path}";
            }
        }

        self::assertSame([], $offenders, \sprintf(
            "Documentation points at %d file(s) that do not exist.\n"
            ."Either fix the path, or — if the doc says plainly that it is not built yet —\n"
            ."add it to %s::ALLOWED_MISSING.\n\n%s",
            \count($offenders),
            self::class,
            implode("\n", $offenders),
        ));
    }

    public function testAllowedMissingEntriesAreStillMissing(): void
    {
        $resurrected = [];

        foreach (array_keys(self::ALLOWED_MISSING) as $path) {
            if (is_file(self::ROOT.'/'.$path) || is_dir(self::ROOT.'/'.$path)) {
                $resurrected[] = $path;
            }
        }

        self::assertSame([], $resurrected, \sprintf(
            "These paths now exist, so they should be dropped from ALLOWED_MISSING\n"
            ."and the documentation that calls them \"planned\" should be updated:\n\n%s",
            implode("\n", $resurrected),
        ));
    }

    /**
     * @return list<string> absolute paths of every markdown document
     */
    private function documents(): array
    {
        $found = [];

        foreach (['README.md', 'PLAN.md', 'TASKS.md'] as $file) {
            $path = realpath(self::ROOT.'/'.$file);
            if ($path !== false) {
                $found[] = $path;
            }
        }

        $docs = realpath(self::ROOT.'/docs');
        if ($docs === false) {
            return $found;
        }

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($docs));
        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->getExtension() === 'md') {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }

    /**
     * Repository-relative paths only: anything rooted at one of the top-level
     * directories and carrying a file extension. Absolute paths (/usr/local/…)
     * and bare directory names are ignored on purpose.
     *
     * @return array<int, string> line number => path
     */
    private function pathsMentionedIn(string $document): array
    {
        $contents = file_get_contents($document);
        self::assertIsString($contents);

        $pattern = '#(?<![\w/.])(?:src|tests|config|bin|templates|public|migrations|translations)'
            .'/[A-Za-z0-9_./-]+\.[A-Za-z0-9]{1,5}#';

        $found = [];

        foreach (explode("\n", $contents) as $index => $line) {
            if (!preg_match_all($pattern, $line, $matches)) {
                continue;
            }

            foreach ($matches[0] as $match) {
                $path = rtrim($match, '.,);:');
                // Globs and wildcards are illustrative, never real paths.
                if (str_contains($path, '*')) {
                    continue;
                }
                $found[$index + 1] = $path;
            }
        }

        return $found;
    }
}
