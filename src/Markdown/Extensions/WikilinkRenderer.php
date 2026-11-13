<?php

/**
 * MD.Scribe 3.3 — wikilink renderer.
 *
 * The link is resolved against the render context (the repository is reached
 * through the `fileExists` callable, exactly like the link-health marker), so
 * the converter stays cacheable. A link whose document does not exist is still
 * rendered — one click creates it — but carries `md-wikilink-missing` so the
 * reader sees the difference. Both classes are on the sanitizer allowlist.
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;
use MdViewer\Files\WikilinkResolver;
use MdViewer\Markdown\ContextHolder;
use MdViewer\Markdown\Extensions\Node\Wikilink;
use MdViewer\Markdown\RenderContext;

final class WikilinkRenderer implements NodeRendererInterface
{
    public function __construct(private readonly ContextHolder $holder)
    {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): \Stringable
    {
        if (!$node instanceof Wikilink) {
            throw new \InvalidArgumentException('Incompatible node');
        }

        $context = $this->holder->current ?? new RenderContext();
        $target = $node->target();
        $anchor = $node->anchor();

        // [[#Heading]] — a jump inside the document being rendered.
        if ($target === '') {
            $text = $node->label() ?? ('#' . (string) $anchor);

            return new HtmlElement(
                'a',
                [
                    'href' => '#' . HeadingProcessor::slugify((string) $anchor),
                    'class' => 'md-wikilink',
                    'title' => '#' . (string) $anchor,
                ],
                $this->escape($text),
            );
        }

        $candidates = WikilinkResolver::candidates($target, $context->docDir);
        if ($candidates === []) {
            // Absolute path, `..` segment or a scheme: never turn it into a link.
            return new HtmlElement(
                'span',
                ['class' => 'md-wikilink md-wikilink-refused', 'title' => 'Link target refused'],
                $this->escape($node->label() ?? $target),
            );
        }

        $resolved = $candidates[0];
        $exists = null;
        foreach ($candidates as $candidate) {
            $state = $context->fileExists($candidate);
            if ($state === true) {
                $resolved = $candidate;
                $exists = true;
                break;
            }
            if ($state === false) {
                $exists = false;
            }
        }

        $href = $context->url(['file' => $resolved]);
        if ($anchor !== null) {
            $href .= '#' . HeadingProcessor::slugify($anchor);
        }

        $label = $node->label();
        if ($label === null) {
            $label = $anchor !== null ? $target . ' § ' . $anchor : $target;
        }

        $attributes = [
            'href' => $href,
            'class' => $exists === false ? 'md-wikilink md-wikilink-missing' : 'md-wikilink',
            'title' => $exists === false
                ? 'Not created yet: ' . $resolved
                : $target . ($anchor !== null ? '#' . $anchor : ''),
        ];

        return new HtmlElement('a', $attributes, $this->escape($label));
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
