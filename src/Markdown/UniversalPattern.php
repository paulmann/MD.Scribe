<?php

/**
 * MD.Viewer 3.0 — universal patterns (ported from MD.Viewer v2.18).
 *
 * Inline code whose content reads like a node/relation chain — "(A) --> [B]",
 * "<-[:REL {props}]->" — is rendered as a small visual chain instead of a
 * code span. The v2.16 fix stands: every branch of the scan loop consumes at
 * least one byte, and an unpairable '<' or '>' stops the scan instead of
 * spinning (that was the 30-second HTTP 500).
 */

declare(strict_types=1);

namespace MdViewer\Markdown;

final class UniversalPattern
{
    /**
     * Parse a code-span body into chain elements, or null when the content
     * is just code.
     *
     * @return list<array{type: string, label: string, wrapper?: string, direction?: string}>|null
     */
    public static function parse(string $pattern): ?array
    {
        $pattern = trim($pattern);
        if ($pattern === '' || preg_match('/[()\[\]{}]|->|<-|--/u', $pattern) !== 1) {
            return null;
        }

        $elements = [];
        $i = 0;
        $len = strlen($pattern);
        $hasNode = false;
        $hasRel = false;

        while ($i < $len) {
            $char = $pattern[$i];
            $prevIndex = $i;

            if ($char === '(') {
                $closePos = strpos($pattern, ')', $i);
                if ($closePos === false) {
                    break;
                }
                $content = substr($pattern, $i + 1, $closePos - $i - 1);
                $label = $content !== '' ? (str_contains($content, ':') ? trim(explode(':', $content, 2)[1]) : trim($content)) : '';
                $elements[] = ['type' => 'node', 'label' => $label, 'wrapper' => 'paren'];
                $hasNode = true;
                $i = $closePos + 1;
                continue;
            }
            if ($char === '[') {
                $closePos = strpos($pattern, ']', $i);
                if ($closePos === false) {
                    break;
                }
                $content = substr($pattern, $i + 1, $closePos - $i - 1);
                $label = trim($content);
                $isRel = preg_match('/(-+>|<-+|--)/u', $pattern) === 1;
                $elements[] = [
                    'type' => $isRel ? 'rel' : 'bracket',
                    'label' => $label,
                    'wrapper' => 'bracket',
                    'direction' => 'undirected',
                ];
                if ($isRel) {
                    $hasRel = true;
                }
                $i = $closePos + 1;
                continue;
            }
            if ($char === '{') {
                $closePos = strpos($pattern, '}', $i);
                if ($closePos === false) {
                    break;
                }
                $elements[] = ['type' => 'curly', 'label' => trim(substr($pattern, $i + 1, $closePos - $i - 1)), 'wrapper' => 'curly'];
                $i = $closePos + 1;
                continue;
            }
            if ($char === '-' || $char === '<' || $char === '>') {
                $hasLeftArrow = false;
                $hasRightArrow = false;
                $relLabel = '';
                $startIdx = $i;
                if ($char === '<' && ($i + 1 < $len) && $pattern[$i + 1] === '-') {
                    $hasLeftArrow = true;
                    $i += 2;
                } elseif ($char === '-') {
                    $i++;
                }
                if ($i < $len && $pattern[$i] === '[') {
                    $closePos = strpos($pattern, ']', $i);
                    if ($closePos !== false) {
                        $inner = substr($pattern, $i + 1, $closePos - $i - 1);
                        $relLabel = str_contains($inner, ':') ? trim(explode(':', $inner, 2)[1]) : trim($inner);
                        $i = $closePos + 1;
                    }
                }
                if ($i < $len && $pattern[$i] === '-') {
                    $i++;
                    if ($i < $len && $pattern[$i] === '>') {
                        $hasRightArrow = true;
                        $i++;
                    }
                } elseif ($i < $len && $pattern[$i] === '>') {
                    $hasRightArrow = true;
                    $i++;
                }
                if ($i > $startIdx && ($hasLeftArrow || $hasRightArrow || $relLabel !== '')) {
                    $direction = match (true) {
                        $hasLeftArrow && $hasRightArrow => 'bidirectional',
                        $hasLeftArrow => 'left',
                        $hasRightArrow => 'right',
                        default => 'undirected',
                    };
                    $elements[] = ['type' => 'rel', 'label' => $relLabel, 'direction' => $direction, 'wrapper' => 'arrow'];
                    $hasRel = true;
                }
                // v2.16 rule: never loop without consuming bytes.
                if ($i === $prevIndex) {
                    break;
                }
                continue;
            }
            $i++;
        }

        if ($elements === []) {
            return null;
        }
        // A chain needs both a node and a relation to be worth visualising.
        return ($hasNode && $hasRel) ? $elements : null;
    }

    /** @param list<array{type: string, label: string, wrapper?: string, direction?: string}> $elements */
    public static function render(array $elements): string
    {
        $html = ['<span class="pattern-chain" role="group" aria-label="Pattern visualization">'];
        foreach ($elements as $el) {
            $label = $el['label'] ?? '';
            $type = $el['type'] ?? 'unknown';
            $wrapper = $el['wrapper'] ?? '';
            if ($type === 'node' || $wrapper === 'paren') {
                $html[] = '<span class="pattern-node" role="term">' . ($label !== '' ? self::e($label) : '&#8226;') . '</span>';
            } elseif ($type === 'bracket' || $wrapper === 'bracket') {
                $html[] = '<span class="pattern-bracket" role="mark">' . ($label !== '' ? self::e($label) : '[]') . '</span>';
            } elseif ($type === 'curly' || $wrapper === 'curly') {
                $html[] = '<span class="pattern-curly" role="note">' . ($label !== '' ? self::e($label) : '{}') . '</span>';
            } elseif ($type === 'rel' || $wrapper === 'arrow') {
                $dir = $el['direction'] ?? 'undirected';
                $relHtml = '<span class="pattern-rel" role="link"><span class="pattern-line"></span>';
                if ($dir === 'left' || $dir === 'bidirectional') {
                    $relHtml .= '<span class="pattern-arrow">&lsaquo;</span>';
                }
                if ($label !== '') {
                    $relHtml .= '<span class="pattern-rel-label">' . self::e($label) . '</span>';
                }
                if ($dir === 'right' || $dir === 'bidirectional') {
                    $relHtml .= '<span class="pattern-arrow">&rsaquo;</span>';
                }
                $relHtml .= '<span class="pattern-line"></span></span>';
                $html[] = $relHtml;
            } else {
                $html[] = '<code class="md-code-inline">' . self::e($label) . '</code>';
            }
        }
        $html[] = '</span>';

        return implode('', $html);
    }

    private static function e(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
