<?php

/**
 * MD.Viewer 3.0 — Mermaid auto-repair (server-side port of the v2.5.2 JS
 * repair engine + the v2.9.2 PHP normalizer, unified).
 *
 * Repairs, in order:
 *  - PlantUML-style class-diagram note blocks  → `note for X "…"`
 *  - literal `\n` inside labels               → `<br/>`
 *  - unclosed `<br>`                          → `<br/>`
 *  - PlantUML `return` in sequence diagrams   → `callee-->>caller: msg`
 *    (orphan returns are commented out, never dropped)
 *  - unquoted parentheses in flowchart node labels → quoted with `#quot;`
 *
 * Every repair is recorded so the client can show English-only diagnostics
 * exactly like v2 did.
 */

declare(strict_types=1);

namespace MdViewer\Markdown;

final class MermaidNormalizer
{
    public const string REPAIR_VERSION = '3.0.0';

    private const array NODE_SHAPES = [['[[', ']]'], ['[(', ')]'], ['([', '])'], ['[', ']'], ['{{', '}}'], ['{', '}'], ['((', '))'], ['(', ')']];

    /**
     * @return array{source: string, fixes: list<array{line:int,rule:string,detail:string,before:string,after:string}>, diagramType: string}
     */
    public static function repair(string $source): array
    {
        $fixes = [];
        $diagramType = self::diagramType($source);
        $isSequence = $diagramType === 'sequenceDiagram';
        $isFlowchart = $diagramType === 'graph' || $diagramType === 'flowchart';
        $prepared = str_replace(["\r\n", "\r"], "\n", $source);

        if ($diagramType === 'classDiagram') {
            $prepared = self::repairClassDiagramNotes($prepared, $fixes);
        }

        $lastCaller = null;
        $lastCallee = null;
        $lines = explode("\n", $prepared);
        foreach ($lines as $position => $original) {
            $lineNumber = $position + 1;
            $current = $original;

            if (str_contains($current, '\\n')) {
                $current = str_replace('\\n', '<br/>', $current);
                $fixes[] = self::fix($lineNumber, 'literal-newline', 'Literal \\n replaced with <br/>', $original, $current);
            }
            if (preg_match('/<br\s*>/i', $current) === 1) {
                $before = $current;
                $current = (string) preg_replace('/<br\s*>/i', '<br/>', $current);
                $fixes[] = self::fix($lineNumber, 'unclosed-br', '<br> replaced with <br/>', $before, $current);
            }

            if ($isSequence) {
                if (preg_match('/^\s*([^\s:]+?)\s*(-->>|->>|-->|->|--x|-x|--\)|-\))\s*([^\s:]+?)\s*:/', $current, $arrow) === 1) {
                    $lastCaller = $arrow[1];
                    $lastCallee = $arrow[3];
                }
                if (preg_match('/^(\s*)return\b\s*(.*)$/', $current, $returnMatch) === 1) {
                    $before = $current;
                    $message = trim($returnMatch[2]);
                    if ($lastCaller !== null && $lastCallee !== null) {
                        $current = $returnMatch[1] . $lastCallee . '-->>' . $lastCaller . ': ' . ($message === '' ? 'return' : $message);
                        $fixes[] = self::fix($lineNumber, 'plantuml-return', 'PlantUML return converted to ' . $lastCallee . '-->>' . $lastCaller, $before, $current);
                    } else {
                        $current = $returnMatch[1] . '%% ' . trim($before);
                        $fixes[] = self::fix($lineNumber, 'plantuml-return-orphan', 'Orphan PlantUML return commented out', $before, $current);
                    }
                }
            } elseif ($isFlowchart && preg_match('/^(style|classDef|class|linkStyle|click|subgraph|end|graph|flowchart|direction|%%)\b/', trim($current)) !== 1) {
                $normalized = self::normalizeLine($current);
                if ($normalized !== $current) {
                    $fixes[] = self::fix($lineNumber, 'unquoted-parentheses', 'Node label containing parentheses was quoted', $current, $normalized);
                    $current = $normalized;
                }
            }

            $lines[$position] = $current;
        }

        return ['source' => implode("\n", $lines), 'fixes' => $fixes, 'diagramType' => $diagramType];
    }

    public static function isMermaidLanguage(string $lang): bool
    {
        return in_array(mb_strtolower(trim($lang), 'UTF-8'), ['mermaid', 'mmd'], true);
    }

    public static function diagramType(string $source): string
    {
        foreach (explode("\n", $source) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '%%')) {
                continue;
            }
            $words = preg_split('/\s+/', $line) ?: [];

            return (string) ($words[0] ?? '');
        }

        return '';
    }

    private static function repairClassDiagramNotes(string $source, array &$fixes): string
    {
        $lines = explode("\n", $source);
        $result = [];
        $count = count($lines);

        for ($index = 0; $index < $count; $index++) {
            if (preg_match('/^(\s*)note\s+(?:right|left)\s+of\s+([A-Za-z_][\w-]*)\s*$/', $lines[$index], $match) !== 1) {
                $result[] = $lines[$index];
                continue;
            }

            $noteLines = [];
            $endIndex = $index + 1;
            while ($endIndex < $count && preg_match('/^\s*end\s+note\s*$/', $lines[$endIndex]) !== 1) {
                $text = trim($lines[$endIndex]);
                if ($text !== '') {
                    $noteLines[] = $text;
                }
                $endIndex++;
            }

            if ($endIndex >= $count) {
                $result[] = $lines[$index];
                continue;
            }

            $noteText = str_replace('"', '#quot;', implode('<br/>', $noteLines));
            $replacement = $match[1] . 'note for ' . $match[2] . ' "' . $noteText . '"';
            $fixes[] = self::fix($index + 1, 'plantuml-class-note', 'PlantUML note block converted to Mermaid note-for syntax', trim($lines[$index]) . ' ... end note', $replacement);
            $result[] = $replacement;
            $index = $endIndex;
        }

        return implode("\n", $result);
    }

    /** Quote node labels that contain unescaped parentheses (v2 rule). */
    private static function normalizeLine(string $line): string
    {
        $result = '';
        $index = 0;
        $length = strlen($line);

        while ($index < $length) {
            if (preg_match('/^[A-Za-z_][\w-]*/', substr($line, $index), $idMatch) !== 1) {
                $result .= $line[$index];
                $index++;
                continue;
            }

            $nodeId = $idMatch[0];
            $afterId = $index + strlen($nodeId);
            $shape = null;

            foreach (self::NODE_SHAPES as [$open, $close]) {
                if (substr($line, $afterId, strlen($open)) !== $open) {
                    continue;
                }
                // Round shapes (…) get balance-aware matching so a label like
                // "End (x)" quotes as a whole instead of splitting at the
                // inner ')' — an improvement over the v2 first-close rule.
                $closeIndex = ($open === '(' && $close === ')')
                    ? self::findBalancedClose($line, $afterId + 1)
                    : strpos($line, $close, $afterId + strlen($open));
                if ($closeIndex !== false) {
                    $shape = ['open' => $open, 'close' => $close, 'closeIndex' => $closeIndex];
                    break;
                }
            }

            if ($shape === null) {
                $result .= $nodeId;
                $index = $afterId;
                continue;
            }

            $labelStart = $afterId + strlen($shape['open']);
            $rawLabel = substr($line, $labelStart, $shape['closeIndex'] - $labelStart);
            $trimmed = trim($rawLabel);
            $needsQuote = $trimmed !== ''
                && !in_array(substr($trimmed, 0, 1), ['"', '`'], true)
                && (str_contains($trimmed, '(') || str_contains($trimmed, ')'));

            $label = $needsQuote ? '"' . str_replace('"', '#quot;', $trimmed) . '"' : $rawLabel;
            $result .= $nodeId . $shape['open'] . $label . $shape['close'];
            $index = $shape['closeIndex'] + strlen($shape['close']);
        }

        return $result;
    }

    /**
     * Index of the ')' that balances the '(' just before $start, or false.
     * Quote-aware: parentheses inside "…" do not count.
     */
    private static function findBalancedClose(string $line, int $start): int|false
    {
        $depth = 1;
        $inQuote = false;
        $len = strlen($line);
        for ($i = $start; $i < $len; $i++) {
            $char = $line[$i];
            if ($char === '"') {
                $inQuote = !$inQuote;
                continue;
            }
            if ($inQuote) {
                continue;
            }
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return false;
    }

    private static function fix(int $line, string $rule, string $detail, string $before, string $after): array
    {
        return ['line' => $line, 'rule' => $rule, 'detail' => $detail, 'before' => trim($before), 'after' => trim($after)];
    }
}
