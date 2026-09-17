<?php

declare(strict_types=1);

namespace Tests\Tour\Infrastructure;

use App\Tour\Infrastructure\SourceSnippetExtractor;
use App\Tour\Infrastructure\YamlTourRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class TourSnippetsResolveTest extends TestCase
{
    #[Test]
    public function allTourSnippetsResolveCorrectly(): void
    {
        $projectDir = __DIR__.'/../../../';
        $configPath = $projectDir.'config/tour.yaml';
        $tourConfig = Yaml::parseFile($configPath);

        $extractor = new SourceSnippetExtractor($projectDir, ['src']);

        foreach ($tourConfig['steps'] as $stepId => $stepConfig) {
            if (!isset($stepConfig['snippets'])) {
                continue;
            }

            foreach ($stepConfig['snippets'] as $snippet) {
                $snippetId = $snippet['id'];
                $filePath = $snippet['file'];

                $fullPath = $projectDir.$filePath;
                self::assertFileExists($fullPath, "File {$filePath} for snippet {$snippetId} does not exist");

                try {
                    $content = $extractor->extract($snippetId, $filePath);
                    self::assertNotEmpty($content, "Snippet {$snippetId} in {$filePath} is empty");
                } catch (\Exception $e) {
                    self::fail("Failed to extract snippet {$snippetId} from {$filePath}: {$e->getMessage()}");
                }
            }
        }
    }
}
