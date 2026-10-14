<?php

/**
 * MD.Viewer 3.0 — document metadata extraction.
 *
 * Frontmatter (YAML-lite), hero title (with the v2 "split by colon" rule),
 * meta description (first meaningful paragraph), word count and reading time.
 */

declare(strict_types=1);

namespace MdViewer\Markdown;

final class ExcerptService
{
    /**
     * Extract YAML-ish frontmatter. Supports scalars, booleans, integers,
     * inline lists ([a, b]) and block lists (- item). Returns
     * [frontmatter, markdownWithoutFrontmatter].
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    public static function extractFrontMatter(string $markdown): array
    {
        if (!str_starts_with($markdown, "---\n") && !str_starts_with($markdown, "---\r\n")) {
            return [[], $markdown];
        }
        $normalized = str_replace("\r\n", "\n", $markdown);
        if (!preg_match('/\A---\n(.*?)\n---\n?/s', $normalized, $m)) {
            return [[], $markdown];
        }

        return [self::parseSimpleYaml($m[1]), substr($normalized, strlen($m[0]))];
    }

    /** @return array<string, mixed> */
    public static function parseSimpleYaml(string $yaml): array
    {
        $data = [];
        $lines = explode("\n", $yaml);
        $currentKey = null;
        foreach ($lines as $line) {
            if (trim($line) === '' || str_starts_with(ltrim($line), '#')) {
                continue;
            }
            // Block list item under $currentKey
            if ($currentKey !== null && preg_match('/^\s+-\s+(.*)$/', $line, $m) === 1) {
                $data[$currentKey][] = self::scalar(trim($m[1]));
                continue;
            }
            if (preg_match('/^([A-Za-z0-9_.\-]+)\s*:\s*(.*)$/', $line, $m) === 1) {
                $key = $m[1];
                $value = trim($m[2]);
                if ($value === '') {
                    $data[$key] = [];
                    $currentKey = $key;
                    continue;
                }
                $currentKey = null;
                if (str_starts_with($value, '[') && str_ends_with($value, ']')) {
                    $inner = trim(substr($value, 1, -1));
                    $data[$key] = $inner === '' ? [] : array_map(
                        static fn (string $v): mixed => self::scalar(trim($v)),
                        explode(',', $inner),
                    );
                    continue;
                }
                $data[$key] = self::scalar($value);
            }
        }

        return $data;
    }

    private static function scalar(string $value): string|int|float|bool|null
    {
        if ($value === '~' || strtolower($value) === 'null') {
            return null;
        }
        if (in_array(strtolower($value), ['true', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array(strtolower($value), ['false', 'no', 'off'], true)) {
            return false;
        }
        if (preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }
        if (preg_match('/^-?\d+\.\d+$/', $value) === 1) {
            return (float) $value;
        }
        $len = strlen($value);
        if ($len >= 2 && (($value[0] === '"' && $value[$len - 1] === '"') || ($value[0] === "'" && $value[$len - 1] === "'"))) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * First level-1 ATX heading outside fenced code (markdown entity-safe).
     */
    public static function firstH1(string $markdown): ?string
    {
        $inFence = false;
        foreach (explode("\n", $markdown) as $line) {
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line) === 1) {
                $inFence = !$inFence;
                continue;
            }
            if (!$inFence && preg_match('/^#\s+(.+?)\s*#*\s*$/', $line, $m) === 1) {
                return self::stripInlineMarkdown(trim($m[1]));
            }
        }

        return null;
    }

    /**
     * Hero title with the v2 "split by colon" rule.
     *
     * @return array{title: string, eyebrow: ?string}
     */
    public static function heroTitle(string $title, bool $splitByColon): array
    {
        if (!$splitByColon) {
            return ['title' => $title, 'eyebrow' => null];
        }
        // Split on the first colon only when both sides are meaningful.
        $pos = strpos($title, ':');
        if ($pos !== false && $pos > 2 && $pos < strlen($title) - 2) {
            $eyebrow = trim(substr($title, 0, $pos));
            $rest = trim(substr($title, $pos + 1));
            if ($eyebrow !== '' && $rest !== '') {
                return ['title' => $rest, 'eyebrow' => $eyebrow];
            }
        }

        return ['title' => $title, 'eyebrow' => null];
    }

    /** First meaningful paragraph → meta description (max ~300 chars). */
    public static function description(string $markdown): string
    {
        $inFence = false;
        $buffer = [];
        foreach (explode("\n", $markdown) as $line) {
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line) === 1) {
                $inFence = !$inFence;
                continue;
            }
            if ($inFence) {
                continue;
            }
            $trim = trim($line);
            if ($trim === '') {
                if ($buffer !== []) {
                    break;
                }
                continue;
            }
            // Skip headings, hr, tables, blockquote markers, list markers, html
            if (preg_match('/^(#{1,6}\s|>|\||-{3,}|\*{3,}|_{3,}|<)/', $trim) === 1) {
                if ($buffer !== []) {
                    break;
                }
                continue;
            }
            $buffer[] = preg_replace('/^\s*[-*+]\s+|\s*\d+[.)]\s+/', '', $trim) ?? $trim;
        }
        if ($buffer === []) {
            return '';
        }
        $text = self::stripInlineMarkdown(implode(' ', $buffer));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;

        return mb_strlen($text) > 300 ? rtrim(mb_substr($text, 0, 300)) . '…' : $text;
    }

    /** Remove inline markdown markup, leaving readable text. */
    public static function stripInlineMarkdown(string $text): string
    {
        $text = preg_replace('/!\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? $text;     // images
        $text = preg_replace('/\[([^\]]*)\]\([^)]*\)/', '$1', $text) ?? $text;      // links
        $text = preg_replace('/\[\^?[^\]]*\]/', '', $text) ?? $text;                // refs/footnotes
        $text = preg_replace('/(`+)([^`]+)\1/', '$2', $text) ?? $text;              // code spans
        $text = preg_replace('/(\*\*|__)(.*?)\1/s', '$2', $text) ?? $text;          // strong
        $text = preg_replace('/(\*|_)([^\s*_][^*_]*?)\1/s', '$2', $text) ?? $text;  // emphasis
        $text = preg_replace('/~~(.*?)~~/s', '$1', $text) ?? $text;                 // strike
        $text = preg_replace('/==(.*?)==/s', '$1', $text) ?? $text;                 // mark
        $text = preg_replace('/:(\w+):/', '$1', $text) ?? $text;                    // emoji codes

        return trim($text);
    }

    /** @return array{words: int, minutes: int} at ~200 wpm */
    public static function readingTime(string $markdown): array
    {
        $plain = self::stripInlineMarkdown($markdown);
        $words = str_word_count($plain);
        // CJK text has no spaces — count CJK characters as words too.
        $cjk = preg_match_all('/[\x{4E00}-\x{9FFF}\x{3040}-\x{30FF}\x{AC00}-\x{D7AF}]/u', $plain) ?: 0;

        return ['words' => $words + $cjk, 'minutes' => max(1, (int) ceil(($words + $cjk) / 200))];
    }

    /**
     * Heading outline of a markdown source (fence-aware).
     *
     * @return list<array{level: int, text: string, anchor: string}>
     */
    public static function headings(string $markdown): array
    {
        $headings = [];
        $inFence = false;
        $seen = [];
        foreach (explode("\n", $markdown) as $line) {
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line) === 1) {
                $inFence = !$inFence;
                continue;
            }
            if ($inFence) {
                continue;
            }
            if (preg_match('/^\s{0,3}(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $m) === 1) {
                $text = trim(self::stripInlineMarkdown($m[2]));
                $anchor = \MdViewer\Markdown\Extensions\HeadingProcessor::slugify($text);
                if ($anchor === '') {
                    $anchor = 'section';
                }
                if (isset($seen[$anchor])) {
                    $anchor .= '-' . ++$seen[$anchor];
                } else {
                    $seen[$anchor] = 0;
                }
                $headings[] = ['level' => strlen($m[1]), 'text' => $text, 'anchor' => $anchor];
            }
        }

        return $headings;
    }

    /**
     * Does the document number its headings manually? (v2 rule: any heading
     * whose text starts with "1." / "1.1" style numbering disables auto-numbering.)
     */
    public static function hasManualNumbering(string $markdown): bool
    {
        $inFence = false;
        foreach (explode("\n", $markdown) as $line) {
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line) === 1) {
                $inFence = !$inFence;
                continue;
            }
            if ($inFence) {
                continue;
            }
            if (preg_match('/^#{1,6}\s+\d+(\.\d+)*\.?\s+\S/u', $line) === 1) {
                return true;
            }
        }

        return false;
    }
}
