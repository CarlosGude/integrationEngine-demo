<?php

declare(strict_types=1);

namespace Tests\Translation;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class TranslationParityTest extends TestCase
{
    public function testAllMessagesExistInBothLanguages(): void
    {
        $enMessages = $this->loadMessages('en');
        $esMessages = $this->loadMessages('es');

        $enKeys = array_keys($enMessages);
        $esKeys = array_keys($esMessages);

        $missingInEs = array_diff($enKeys, $esKeys);
        $missingInEn = array_diff($esKeys, $enKeys);

        self::assertEmpty(
            $missingInEs,
            sprintf('Keys missing in Spanish: %s', implode(', ', $missingInEs)),
        );

        self::assertEmpty(
            $missingInEn,
            sprintf('Keys missing in English: %s', implode(', ', $missingInEn)),
        );
    }

    /**
     * @return array<string, string>
     */
    private function loadMessages(string $locale): array
    {
        $filePath = sprintf('%s/translations/messages.%s.yaml', $this->getProjectDir(), $locale);
        if (!file_exists($filePath)) {
            return [];
        }

        $yaml = Yaml::parseFile($filePath);

        return is_array($yaml) ? $yaml : [];
    }

    private function getProjectDir(): string
    {
        return dirname(__DIR__, 2);
    }
}
