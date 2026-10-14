<?php

/**
 * MD.Viewer 3.0 — paragraph renderer.
 *
 * A paragraph whose only child is an image renders without the <p> wrapper so
 * the figure/figcaption markup stays valid HTML. Attributes assigned by the
 * AttributesExtension ({.class #id}) are preserved.
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class ParagraphRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (!$node instanceof Paragraph) {
            return '';
        }

        $first = $node->firstChild();
        if ($first instanceof Image && $node->lastChild() === $first) {
            return $childRenderer->renderNodes($node->children());
        }

        $attrs = $node->data->get('attributes', []);
        $attrHtml = '';
        if (is_array($attrs)) {
            foreach ($attrs as $name => $value) {
                if (is_string($name) && (is_string($value) || is_numeric($value))) {
                    $attrHtml .= ' ' . htmlspecialchars($name, ENT_QUOTES) . '="' . htmlspecialchars((string) $value, ENT_QUOTES) . '"';
                }
            }
        }

        $inner = $childRenderer->renderNodes($node->children());

        return '<p' . $attrHtml . '>' . $inner . '</p>';
    }
}
