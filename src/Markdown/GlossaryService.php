<?php

/**
 * MD.Viewer 3.0 — glossary service (port of the v2.4 engine).
 *
 * A document may contain one or more "glossary" tables — any table whose
 * first column has a non-empty header. Terms from the first column (with the
 * "Base term (ALIAS)" expansion rule) become hover tooltips everywhere in the
 * rendered document. The tooltip content itself is NOT duplicated: the JS
 * engine reads it live from the table cells, which carry stable ids
 * (gd-{slug}-{row}-{col}) — the same contract as v2, so tooltips.js ports 1:1.
 */

declare(strict_types=1);

namespace MdViewer\Markdown;

final class GlossaryService
{
    /**
     * Parse glossary tables from the markdown source.
     *
     * @return array<string, array{slug: string, row: int, cols: int, display: string}> keyed by lowercase term
     */
    public static function parse(string $markdown): array
    {
        if ($markdown === '') {
            return [];
        }

        $lines = explode("\n", $markdown);
        $n = count($lines);
        $glossary = [];
        $tableIndex = 0;
        $i = 0;
        $inFence = false;
        $fenceChar = '';
        $fenceLen = 0;

        while ($i < $n) {
            $line = $lines[$i];

            // Fence tracking so tables inside code blocks never count.
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line, $fm) === 1) {
                $char = $fm[1][0];
                $len = strlen($fm[1]);
                if (!$inFence) {
                    $inFence = true;
                    $fenceChar = $char;
                    $fenceLen = $len;
                } elseif ($char === $fenceChar && $len >= $fenceLen) {
                    $inFence = false;
                }
                $i++;
                continue;
            }
            if ($inFence) {
                $i++;
                continue;
            }

            if (!self::isTableLine($line)) {
                $i++;
                continue;
            }

            $block = [];
            while ($i < $n && self::isTableLine($lines[$i])) {
                $block[] = $lines[$i];
                $i++;
            }
            if (count($block) < 2) {
                continue;
            }

            $tableIndex++;
            $slug = 'tbl-' . $tableIndex;
            $headers = self::parseTableRow((string) $block[0]);
            $colCount = count($headers);
            if ($colCount < 2) {
                continue;
            }
            // A glossary always has a named term column.
            if (self::plainText((string) $headers[0]) === '') {
                continue;
            }

            $bodyStart = self::isAlignmentSeparator((string) $block[1]) ? 2 : 1;
            foreach (array_slice($block, $bodyStart) as $rowIndex => $rowLine) {
                $cells = self::parseTableRow($rowLine);
                $plain = self::plainText((string) ($cells[0] ?? ''));
                if ($plain === '') {
                    continue;
                }
                foreach (self::expandAliases($plain) as $alias) {
                    $key = mb_strtolower($alias, 'UTF-8');
                    if ($key === '' || isset($glossary[$key])) {
                        continue;
                    }
                    $glossary[$key] = [
                        'slug' => $slug,
                        'row' => $rowIndex,
                        'cols' => $colCount,
                        'display' => $alias,
                    ];
                }
            }
        }

        return $glossary;
    }

    /**
     * "Base term (ALIAS)" → ["Base term (ALIAS)", "Base term", "ALIAS"].
     *
     * @return list<string>
     */
    public static function expandAliases(string $plain): array
    {
        $terms = [trim($plain)];
        if (preg_match('/^(.+?)\s*\(([^)]+)\)\s*$/u', $plain, $m) === 1) {
            $base = trim($m[1]);
            $alias = trim($m[2]);
            if ($base !== '' && !in_array($base, $terms, true)) {
                $terms[] = $base;
            }
            if ($alias !== '' && !in_array($alias, $terms, true)) {
                $terms[] = $alias;
            }
        }

        return array_values(array_filter($terms, static fn (string $t): bool => $t !== ''));
    }

    /**
     * Inject glossary-term spans into rendered HTML (tokenizer port of v2):
     * text nodes outside <a>, <code>, <pre>, <script>, <style>, <th> and the
     * first-column glossary cells get `glossary-term` spans. Longest terms
     * match first.
     *
     * @param array<string, array{slug: string, row: int, cols: int, display: string}> $glossary
     */
    public static function apply(string $html, array $glossary): string
    {
        if ($glossary === [] || $html === '') {
            return $html;
        }

        $terms = array_keys($glossary);
        usort($terms, static fn (string $a, string $b): int => mb_strlen($b, 'UTF-8') <=> mb_strlen($a, 'UTF-8'));
        // Cap combined-pattern size (ReDoS/perf guard).
        $terms = array_slice($terms, 0, 400);
        $patterns = array_map(static fn (string $t): string => preg_quote($t, '/'), $terms);
        $combined = '/(?<!\p{L})(?<!\p{N})(' . implode('|', $patterns) . ')(?!\p{L})(?!\p{N})/iu';

        $protectedTags = ['a', 'code', 'pre', 'script', 'style', 'th'];
        $depth = [];
        $gcDepth = 0;
        $result = '';
        $pos = 0;
        $len = strlen($html);

        while ($pos < $len) {
            if ($html[$pos] !== '<') {
                $end = strpos($html, '<', $pos);
                $text = $end === false ? substr($html, $pos) : substr($html, $pos, $end - $pos);
                $pos = $end === false ? $len : $end;

                if ($depth === [] && $gcDepth === 0 && $text !== '') {
                    $text = (string) preg_replace_callback(
                        $combined,
                        static function (array $m) use ($glossary): string {
                            $key = mb_strtolower($m[1], 'UTF-8');
                            $info = $glossary[$key] ?? null;
                            if ($info === null) {
                                return $m[1];
                            }
                            $ref = htmlspecialchars($info['slug'], ENT_QUOTES) . ':' . $info['row'] . ':' . $info['cols'];

                            return '<span class="glossary-term" data-gterm="' . $ref . '">' . $m[1] . '</span>';
                        },
                        $text,
                    );
                }
                $result .= $text;
                continue;
            }

            $end = strpos($html, '>', $pos);
            if ($end === false) {
                $result .= substr($html, $pos);
                break;
            }
            $tag = substr($html, $pos, $end - $pos + 1);
            $pos = $end + 1;
            $result .= $tag;

            $inner = trim(substr($tag, 1, -1));
            if ($inner === '' || $inner[0] === '!' || $inner[0] === '?') {
                continue;
            }
            $isClose = $inner[0] === '/';
            $isSelf = str_ends_with($inner, '/');
            $tagName = strtolower((string) preg_replace('/[\s\/>].*$/s', '', ltrim($inner, '/')));

            if (!$isClose && $tagName === 'td' && str_contains($tag, 'data-gc="1"')) {
                $gcDepth++;
                continue;
            }
            if ($isClose && $tagName === 'td' && $gcDepth > 0) {
                $gcDepth--;
                continue;
            }
            if (!$isClose && !$isSelf && in_array($tagName, $protectedTags, true)) {
                $depth[] = $tagName;
            } elseif ($isClose && $depth !== [] && end($depth) === $tagName) {
                array_pop($depth);
            }
        }

        return $result;
    }

    // ── Table line helpers (v2 semantics) ──────────────────────────────────

    public static function isTableLine(string $line): bool
    {
        $t = trim($line);

        return $t !== '' && str_starts_with($t, '|') && str_contains($t, '|');
    }

    /** @return list<string> */
    public static function parseTableRow(string $line): array
    {
        $t = trim($line);
        if (str_starts_with($t, '|')) {
            $t = substr($t, 1);
        }
        if (str_ends_with($t, '|')) {
            $t = substr($t, 0, -1);
        }
        // Respect escaped pipes inside cells.
        $cells = preg_split('/(?<!\\\\)\|/', $t) ?: [];

        return array_map(static fn (string $c): string => trim(str_replace('\\|', '|', $c)), $cells);
    }

    public static function isAlignmentSeparator(string $line): bool
    {
        $t = trim($line);
        if (!str_contains($t, '|') || !str_contains($t, '-')) {
            return false;
        }
        foreach (self::parseTableRow($t) as $c) {
            $c = trim($c);
            if ($c === '' || preg_match('/^:?-{1,}:?$/', $c) !== 1) {
                return false;
            }
        }

        return true;
    }

    public static function plainText(string $raw): string
    {
        $raw = trim($raw);
        $raw = (string) preg_replace('/(\*\*|__|~~|`+)/u', '', $raw);
        $raw = (string) preg_replace('/(?<!\*)\*([^*]+)\*(?!\*)/u', '$1', $raw);
        $raw = (string) preg_replace('/(?<!_)_([^_]+)_(?!_)/u', '$1', $raw);

        return trim($raw);
    }
}
