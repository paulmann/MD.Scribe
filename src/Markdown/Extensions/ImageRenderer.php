<?php

/**
 * MD.Viewer 3.0 — image renderer.
 *
 * Relative images resolve against the document directory and are served
 * through the viewer's media route (works even when the browse root is not
 * the web root). A title becomes a figure caption. Lazy-loaded by default;
 * lightbox-enabled via data attribute.
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use MdViewer\Markdown\ContextHolder;
use MdViewer\Markdown\PathUtils;

final class ImageRenderer implements NodeRendererInterface
{
    public function __construct(private readonly ContextHolder $holder)
    {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (!$node instanceof Image) {
            return '';
        }

        $ctx = $this->holder->current;
        if ($ctx !== null && !$ctx->feature('FEATURE_IMAGES', true)) {
            return '';
        }

        $src = (string) $node->getUrl();
        $alt = self::altText($node, $childRenderer);
        $title = $node->getTitle();

        // Relative (non-scheme, non-absolute) → media route
        $isRelative = $src !== ''
            && !str_starts_with($src, '/')
            && preg_match('/^[a-z][a-z0-9+.\-]*:/i', $src) !== 1
            && !str_starts_with($src, 'data:');
        if ($isRelative && $ctx !== null) {
            $resolved = PathUtils::joinRelative($ctx->docDir, $src);
            if ($resolved === null) {
                return '<span class="md-blocked-embed">🖼 Image path escapes the document root</span>';
            }
            $src = $ctx->mediaUrl($resolved);
        }

        if ($ctx !== null) {
            $ctx->hasImages = true;
        }

        $lightbox = ($ctx?->feature('FEATURE_LIGHTBOX', true) ?? true) && !str_starts_with($src, 'data:')
            ? ' data-lightbox="1"'
            : '';

        $img = '<img src="' . htmlspecialchars($src, ENT_QUOTES | ENT_SUBSTITUTE) . '"'
            . ' alt="' . htmlspecialchars($alt, ENT_QUOTES | ENT_SUBSTITUTE) . '"'
            . ($title !== null && $title !== '' ? ' title="' . htmlspecialchars($title, ENT_QUOTES) . '"' : '')
            . ' class="md-img" loading="lazy" decoding="async"' . $lightbox . '>';

        if ($title !== null && trim($title) !== '' && trim($title) !== $alt) {
            return '<figure class="md-figure">' . $img
                . '<figcaption>' . htmlspecialchars($title, ENT_QUOTES) . '</figcaption>'
                . '</figure>';
        }

        return $img;
    }

    private static function altText(Image $node, ChildNodeRendererInterface $childRenderer): string
    {
        $rendered = $childRenderer->renderNodes($node->children());
        $text = trim(strip_tags($rendered));

        return $text !== '' ? $text : (string) $node->getUrl();
    }
}
