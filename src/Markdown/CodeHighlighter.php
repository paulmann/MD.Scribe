<?php

/**
 * MD.Viewer 3.0 — server-side syntax highlighting via scrivo/highlight.php.
 *
 * No client JS, no CDN: the highlighted HTML ships in the response and is
 * cached with the document. Unknown languages fall back to escaped plain
 * text — never an exception, never a broken page.
 */

declare(strict_types=1);

namespace MdViewer\Markdown;

final class CodeHighlighter
{
    private static ?\Highlight\Highlighter $hl = null;

    /** Common info-string aliases → highlight.php language ids. */
    private const array ALIASES = [
        'js' => 'javascript', 'mjs' => 'javascript', 'cjs' => 'javascript', 'jsx' => 'javascript',
        'ts' => 'typescript', 'tsx' => 'typescript',
        'py' => 'python', 'python3' => 'python', 'py3' => 'python',
        'rb' => 'ruby', 'sh' => 'bash', 'zsh' => 'bash', 'shell' => 'bash', 'console' => 'bash',
        'yml' => 'yaml', 'golang' => 'go', 'rs' => 'rust', 'cs' => 'csharp', 'c#' => 'csharp',
        'c++' => 'cpp', 'h' => 'cpp', 'hpp' => 'cpp', 'html' => 'xml', 'vue' => 'xml',
        'svg' => 'xml', 'plist' => 'xml', 'ps1' => 'powershell', 'powershell' => 'powershell',
        'docker' => 'dockerfile', 'make' => 'makefile', 'md' => 'markdown', 'markdown' => 'markdown',
        'objective-c' => 'objectivec', 'objc' => 'objectivec', 'kt' => 'kotlin', 'kts' => 'kotlin',
        'plaintext' => '', 'text' => '', 'txt' => '', 'plain' => '', 'code' => '',
    ];

    /**
     * @return array{html: string, language: string} html is escaped+highlighted
     */
    public static function highlight(string $code, string $lang): array
    {
        $lang = strtolower(trim($lang));
        // "php title=…" style info strings: first word only.
        $lang = preg_split('/\s+/', $lang)[0] ?? '';
        $lang = self::ALIASES[$lang] ?? $lang;

        if ($lang === '') {
            return ['html' => htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'language' => ''];
        }

        try {
            $hl = self::hl();
            $result = $hl->highlight($lang, $code);

            return ['html' => $result->value, 'language' => $result->language];
        } catch (\Throwable) {
            // Unknown language or highlighter failure — plain escaped output.
            return ['html' => htmlspecialchars($code, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), 'language' => $lang];
        }
    }

    /** Display label for the code block bar (the raw info word, uppercased). */
    public static function label(string $lang): string
    {
        $lang = strtolower(trim($lang));
        $lang = preg_split('/\s+/', $lang)[0] ?? '';
        if ($lang === '' || isset(self::ALIASES[$lang]) && self::ALIASES[$lang] === '') {
            return 'TEXT';
        }

        return strtoupper($lang);
    }

    private static function hl(): \Highlight\Highlighter
    {
        if (self::$hl === null) {
            self::$hl = new \Highlight\Highlighter();
        }

        return self::$hl;
    }
}
