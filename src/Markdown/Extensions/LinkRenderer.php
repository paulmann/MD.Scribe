<?php

/**
 * MD.Viewer 3.0 — link renderer.
 *
 *  - relative links that point at another .md document are rewritten to the
 *    viewer route (and marked `md-link-missing` when the target does not
 *    exist — dead links become visible instead of 404ing silently);
 *  - external links open in a new tab with rel="noopener noreferrer";
 *  - anything with a dangerous scheme degrades to plain text (belt and
 *    braces — league already refuses unsafe links, the sanitizer checks again).
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use MdViewer\Markdown\ContextHolder;
use MdViewer\Markdown\PathUtils;
use MdViewer\View\Icons;

final class LinkRenderer implements NodeRendererInterface
{
    public function __construct(private readonly ContextHolder $holder)
    {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (!$node instanceof Link) {
            return '';
        }

        $url = (string) $node->getUrl();
        $title = $node->getTitle();
        $inner = $childRenderer->renderNodes($node->children());

        if (preg_match('/^[a-z][a-z0-9+.\-]*:/i', $url) === 1) {
            $scheme = strtolower((string) preg_replace('/:.*$/', '', $url));
            if (!in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
                // Dangerous/unknown scheme — render the text only.
                return '<span class="md-link-blocked">' . $inner . '</span>';
            }
            $external = $scheme === 'http' || $scheme === 'https';
            $class = 'md-link' . ($external ? ' md-link-ext' : '');
            $attrs = $external ? ' target="_blank" rel="noopener noreferrer"' : '';
            if ($external) {
                $inner .= Icons::svg('external-link', 'h-3 w-3 ml-0.5 inline-block align-baseline md-ext-icon');
            }

            return $this->anchor($url, $inner, $class, $title, $attrs);
        }

        // Fragment-only anchor
        if (str_starts_with($url, '#')) {
            return $this->anchor($url, $inner, 'md-link md-link-anchor', $title);
        }

        // Site-absolute path — keep as authored.
        if (str_starts_with($url, '/')) {
            return $this->anchor($url, $inner, 'md-link', $title);
        }

        // Relative reference — resolve against the document's directory.
        $ctx = $this->holder->current;
        [$path, $fragment] = PathUtils::splitFragment($url);
        $resolved = $ctx !== null ? PathUtils::joinRelative($ctx->docDir, $path) : null;

        if ($resolved === null) {
            return '<span class="md-link-blocked">' . $inner . '</span>';
        }

        if (str_ends_with(strtolower($resolved), '.md') || str_ends_with(strtolower($resolved), '.markdown')) {
            $href = $ctx->url(['file' => $resolved]) . ($fragment !== null && $fragment !== '' ? '#' . rawurlencode($fragment) : '');
            $missing = $ctx->fileExists($resolved);
            $class = 'md-link md-link-doc' . ($missing === false ? ' md-link-missing' : '');

            return $this->anchor($href, $inner, $class, $title);
        }

        // Relative non-markdown asset: serve through the media route when the
        // viewer can, otherwise leave the path as authored.
        if ($ctx !== null && $resolved !== '' && !str_contains($resolved, '://')) {
            $href = $ctx->mediaUrl($resolved) . ($fragment !== null && $fragment !== '' ? '#' . rawurlencode($fragment) : '');

            return $this->anchor($href, $inner, 'md-link', $title);
        }

        return $this->anchor($url, $inner, 'md-link', $title);
    }

    private function anchor(string $href, string $inner, string $class, ?string $title = null, string $extraAttrs = ''): string
    {
        $html = '<a href="' . htmlspecialchars($href, ENT_QUOTES | ENT_SUBSTITUTE) . '" class="' . $class . '"';
        if ($title !== null && $title !== '') {
            $html .= ' title="' . htmlspecialchars($title, ENT_QUOTES) . '"';
        }

        return $html . $extraAttrs . '>' . $inner . '</a>';
    }
}
