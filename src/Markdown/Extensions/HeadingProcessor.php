<?php

/**
 * MD.Viewer 3.0 — DocumentParsedEvent listener: assigns a GitHub-style anchor
 * id to every heading, computes hierarchical numbers (1, 1.1, 1.1.2 …) when
 * auto-numbering is on, and collects the heading outline for the TOC sidebar.
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Node\Inline\Text;
use MdViewer\Markdown\ContextHolder;
use MdViewer\Markdown\Extensions\Node\MathInline;

final class HeadingProcessor
{
    public function __construct(private readonly ContextHolder $holder)
    {
    }

    public function __invoke(DocumentParsedEvent $event): void
    {
        $ctx = $this->holder->current;
        if ($ctx === null) {
            return;
        }

        $document = $event->getDocument();
        $counters = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0];
        $seen = [];

        foreach ($document->iterator() as $node) {
            if (!$node instanceof Heading) {
                continue;
            }
            $level = min(6, max(1, $node->getLevel()));
            $text = self::plainText($node);

            $anchor = self::slugify($text);
            if ($anchor === '') {
                $anchor = 'section';
            }
            if (isset($seen[$anchor])) {
                $seen[$anchor]++;
                $anchor .= '-' . $seen[$anchor];
            } else {
                $seen[$anchor] = 0;
            }

            $number = null;
            if ($ctx->autoNumbering) {
                $counters[$level]++;
                for ($deeper = $level + 1; $deeper <= 6; $deeper++) {
                    $counters[$deeper] = 0;
                }
                $parts = [];
                for ($l = 1; $l <= $level; $l++) {
                    $parts[] = $counters[$l];
                }
                $number = implode('.', $parts);
            }

            $node->data->set('mdv_id', $anchor);
            $node->data->set('mdv_number', $number);

            $ctx->headings[] = [
                'level' => $level,
                'text' => $text,
                'anchor' => $anchor,
                'number' => $number,
            ];
        }
    }

    /** Visible text of a heading (math TeX included as-is, code literals too). */
    public static function plainText(\League\CommonMark\Node\Node $node): string
    {
        $out = '';
        foreach ($node->iterator() as $child) {
            if ($child instanceof Text) {
                $out .= $child->getLiteral();
            } elseif ($child instanceof Code) {
                $out .= $child->getLiteral();
            } elseif ($child instanceof MathInline) {
                $out .= $child->tex();
            }
        }

        return trim(preg_replace('/\s+/u', ' ', $out) ?? $out);
    }

    /** GitHub-compatible heading slug. */
    public static function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s_\-]/u', '', $text) ?? $text;
        $text = preg_replace('/[\s_]+/u', '-', $text) ?? $text;

        return trim((string) $text, '-');
    }
}
