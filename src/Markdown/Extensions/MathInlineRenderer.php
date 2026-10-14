<?php

/**
 * MD.Viewer 3.0 — renderers for the small custom inline nodes
 * (math / superscript / subscript). The TeX payload travels as escaped text
 * content; the client-side KaTeX loader renders it from textContent, which
 * keeps CSP happy (no inline scripts) and avoids delimiter ambiguity.
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use MdViewer\Markdown\Extensions\Node\MathInline;
use MdViewer\Markdown\Extensions\Node\SubInline;
use MdViewer\Markdown\Extensions\Node\SupInline;

final class MathInlineRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (!$node instanceof MathInline) {
            return '';
        }

        return '<span class="mdx-math mdx-math-inline" data-mdx-math="inline">'
            . htmlspecialchars($node->tex(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</span>';
    }
}
