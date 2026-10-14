<?php

/**
 * MD.Viewer 3.0 — source pre-processing.
 *
 * Runs *before* league/commonmark and uses the placeholder technique proven
 * in MD.Viewer v2: byte-safe tokens that cannot collide with document text
 * stand in for constructs the CommonMark block parser cannot express
 * directly (multi-line $$…$$ math, the [TOC] placeholder). Tokens are
 * swapped back after rendering.
 *
 * The fence state machine follows CommonMark §4.5 exactly as the v2
 * stripCodeBlocks() did: the closing fence must use the same character and
 * be at least as long as the opening fence — so ````-fenced examples that
 * contain ``` stay intact.
 */

declare(strict_types=1);

namespace MdViewer\Markdown;

final class SourcePreprocessor
{
    public const string MATH_TOKEN = '@@MDVMATH%d@@';
    public const string TOC_TOKEN = '@@MDVTOC@@';

    /**
     * @return array{source: string, mathBlocks: list<string>}
     */
    public static function process(string $markdown): array
    {
        $mathBlocks = [];
        $lines = explode("\n", $markdown);
        $out = [];
        $fenceChar = null;
        $fenceLen = 0;
        $mathBuffer = null;

        foreach ($lines as $line) {
            // Inside a $$…$$ block?
            if ($mathBuffer !== null) {
                $endPos = strpos($line, '$$');
                if ($endPos !== false) {
                    $mathBuffer[] = substr($line, 0, $endPos);
                    $mathBlocks[] = self::cleanMath(implode("\n", $mathBuffer));
                    $mathBuffer = null;
                    $index = count($mathBlocks) - 1;
                    $rest = trim(substr($line, $endPos + 2));
                    $out[] = sprintf(self::MATH_TOKEN, $index);
                    if ($rest !== '') {
                        $out[] = $rest;
                    }
                    continue;
                }
                $mathBuffer[] = $line;
                continue;
            }

            // Fence tracking (CommonMark §4.5)
            if (preg_match('/^(\s{0,3})(`{3,}|~{3,})/', $line, $m) === 1) {
                $char = $m[2][0];
                $len = strlen($m[2]);
                if ($fenceChar === null) {
                    $fenceChar = $char;
                    $fenceLen = $len;
                } elseif ($char === $fenceChar && $len >= $fenceLen && trim(substr($line, strpos($line, $m[2]) + $len)) === '') {
                    $fenceChar = null;
                    $fenceLen = 0;
                }
                $out[] = $line;
                continue;
            }
            if ($fenceChar !== null) {
                $out[] = $line;
                continue;
            }

            // [TOC] placeholder on its own line
            if (preg_match('/^\s{0,3}\[TOC\]\s*$/i', $line) === 1) {
                $out[] = self::TOC_TOKEN;
                continue;
            }

            // $$…$$ block start (possibly closed on the same line)
            $trimmed = ltrim($line);
            if (str_starts_with($trimmed, '$$') && preg_match('/^\s{0,3}/', $line) === 1) {
                $afterOpen = substr($trimmed, 2);
                $endPos = strpos($afterOpen, '$$');
                if ($endPos !== false) {
                    // Single-line $$x$$
                    $tex = self::cleanMath(substr($afterOpen, 0, $endPos));
                    if ($tex !== '') {
                        $mathBlocks[] = $tex;
                        $out[] = sprintf(self::MATH_TOKEN, count($mathBlocks) - 1);
                        $rest = trim(substr($afterOpen, $endPos + 2));
                        if ($rest !== '') {
                            $out[] = $rest;
                        }
                        continue;
                    }
                }
                // Multi-line block opens
                $mathBuffer = [$afterOpen];
                continue;
            }

            $out[] = $line;
        }

        // Unterminated $$ — treat the collected lines as one math block anyway
        // (friendlier than dropping the author's content on the floor).
        if ($mathBuffer !== null) {
            $mathBlocks[] = self::cleanMath(implode("\n", $mathBuffer));
            $out[] = sprintf(self::MATH_TOKEN, count($mathBlocks) - 1);
        }

        return ['source' => implode("\n", $out), 'mathBlocks' => $mathBlocks];
    }

    private static function cleanMath(string $tex): string
    {
        return trim(str_replace('\\$', '$', $tex));
    }

    /** Swap rendered tokens back into final markup. */
    public static function restore(string $html, array $mathBlocks): string
    {
        // Block token usually lands inside a <p> — unwrap the paragraph.
        $html = (string) preg_replace_callback(
            '/<p>\s*@@MDVMATH(\d+)@@\s*<\/p>/',
            static fn (array $m): string => self::mathDiv($mathBlocks, (int) $m[1]),
            $html,
        );
        $html = (string) preg_replace_callback(
            '/@@MDVMATH(\d+)@@/',
            static fn (array $m): string => self::mathDiv($mathBlocks, (int) $m[1]),
            $html,
        );

        return $html;
    }

    private static function mathDiv(array $mathBlocks, int $index): string
    {
        $tex = $mathBlocks[$index] ?? null;
        if ($tex === null) {
            return '';
        }

        return '<div class="mdx-math mdx-math-block" data-mdx-math="block">'
            . htmlspecialchars($tex, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</div>';
    }
}
