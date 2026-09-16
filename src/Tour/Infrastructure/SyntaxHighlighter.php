<?php

declare(strict_types=1);

namespace App\Tour\Infrastructure;

final class SyntaxHighlighter
{
    /** @var list<string> */
    private const PHP_KEYWORDS = [
        'abstract', 'and', 'array', 'as', 'break', 'callable', 'case', 'catch', 'class',
        'const', 'continue', 'declare', 'default', 'die', 'do', 'echo', 'else',
        'elseif', 'empty', 'enddeclare', 'endfor', 'endforeach', 'endif', 'endswitch',
        'endwhile', 'eval', 'exit', 'extends', 'final', 'finally', 'fn', 'for', 'foreach',
        'function', 'global', 'goto', 'if', 'implements', 'include', 'include_once',
        'instanceof', 'insteadof', 'interface', 'isset', 'list', 'match', 'namespace',
        'new', 'or', 'print', 'private', 'protected', 'public', 'readonly', 'require',
        'require_once', 'return', 'static', 'switch', 'throw', 'trait', 'try', 'unset',
        'use', 'var', 'while', 'xor', 'yield', 'true', 'false', 'null',
    ];

    public function highlight(string $code, string $language = 'php'): string
    {
        if ($language !== 'php') {
            return htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        }

        return $this->highlightPhp($code);
    }

    private function highlightPhp(string $code): string
    {
        $code = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

        foreach (self::PHP_KEYWORDS as $keyword) {
            $pattern = '/\b' . preg_quote($keyword, '/') . '\b/';
            /** @var string */
            $code = preg_replace(
                $pattern,
                '<span class="keyword">' . $keyword . '</span>',
                $code,
            );
        }

        /** @var string */
        $code = preg_replace(
            '/(\$[a-zA-Z_][a-zA-Z0-9_]*)/',
            '<span class="variable">$1</span>',
            $code,
        );

        /** @var string */
        $code = preg_replace(
            '/(\'[^\']*\'|"[^"]*")/',
            '<span class="string">$1</span>',
            $code,
        );

        /** @var string */
        return preg_replace(
            '%(//[^\n]*|/\*.*?\*/)%s',
            '<span class="comment">$1</span>',
            $code,
        );
    }
}
