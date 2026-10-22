<?php

/**
 * MD.Viewer 3.0 — blockquote renderer with GitHub-style alerts.
 *
 * `> [!NOTE]` / `[!TIP]` / `[!IMPORTANT]` / `[!WARNING]` / `[!CAUTION]`
 * become accessible callouts with icons; everything else renders as a classic
 * quote with a copy control (the v2 "quote copy" behaviour).
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use MdViewer\Markdown\ContextHolder;
use MdViewer\View\Icons;

final class AlertBlockQuoteRenderer implements NodeRendererInterface
{
    private const array ALERTS = [
        'NOTE' => ['icon' => 'info', 'label' => 'Note'],
        'TIP' => ['icon' => 'lightbulb', 'label' => 'Tip'],
        'IMPORTANT' => ['icon' => 'sparkles', 'label' => 'Important'],
        'WARNING' => ['icon' => 'triangle-alert', 'label' => 'Warning'],
        'CAUTION' => ['icon' => 'octagon-alert', 'label' => 'Caution'],
    ];

    public function __construct(private readonly ContextHolder $holder)
    {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (!$node instanceof BlockQuote) {
            return '';
        }

        $enabled = $this->holder->current?->feature('FEATURE_ALERTS', true) ?? true;
        if ($enabled) {
            $type = self::detectAlert($node);
            if ($type !== null) {
                $meta = self::ALERTS[$type];
                $inner = $childRenderer->renderNodes($node->children());

                return '<div class="md-alert md-alert-' . strtolower($type) . '" data-alert="' . strtolower($type) . '" role="note">'
                    . '<p class="md-alert-title">' . Icons::svg($meta['icon'], 'h-4 w-4')
                    . '<span>' . $meta['label'] . '</span></p>'
                    . '<div class="md-alert-body">' . $inner . '</div>'
                    . '</div>';
            }
        }

        $inner = $childRenderer->renderNodes($node->children());

        if ($this->holder->current?->embedMode ?? false) {
            // External page: no viewer-only copy control, just a quote.
            return '<blockquote class="md-quote">' . $inner . '</blockquote>';
        }

        return '<blockquote class="md-quote">'
            . '<button type="button" class="md-copy-btn md-quote-copy" data-copy-type="quote" aria-label="Copy quote" title="Copy quote">'
            . Icons::svg('text-quote', 'h-3.5 w-3.5') . '</button>'
            . $inner
            . '</blockquote>';
    }

    /**
     * Detect + strip a leading "[!TYPE]" marker. Mutates the AST so the
     * remaining content renders normally inside the callout.
     */
    private static function detectAlert(BlockQuote $node): ?string
    {
        $first = $node->firstChild();
        if (!$first instanceof Paragraph) {
            return null;
        }
        $text = $first->firstChild();
        if (!$text instanceof Text) {
            return null;
        }
        $literal = (string) $text->getLiteral();
        if (preg_match('/^\s{0,3}\[!(NOTE|TIP|IMPORTANT|WARNING|CAUTION)\]\s*(\n|$)/i', $literal, $m) !== 1) {
            return null;
        }

        $type = strtoupper($m[1]);
        $rest = ltrim(substr($literal, strlen($m[0])), "\n");
        if (trim($rest) === '') {
            $text->detach();
            // Drop the softbreak that followed "[!TYPE]" so the callout body
            // does not start with a stray <br>.
            $next = $first->firstChild();
            if ($next instanceof \League\CommonMark\Node\Inline\Newline) {
                $next->detach();
            }
        } else {
            $text->setLiteral($rest);
        }

        return $type;
    }
}
