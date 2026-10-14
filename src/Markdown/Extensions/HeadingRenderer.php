<?php

/**
 * MD.Viewer 3.0 — heading renderer: id anchor, optional number, hover
 * permalink (the # control every 2026 docs site has).
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

final class HeadingRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (!$node instanceof Heading) {
            return '';
        }

        $level = min(6, max(1, $node->getLevel()));
        $id = (string) ($node->data->get('mdv_id') ?? '');
        $number = $node->data->get('mdv_number');
        $inner = $childRenderer->renderNodes($node->children());

        $html = '<h' . $level . ' class="md-h md-h' . $level . '"';
        if ($id !== '') {
            $html .= ' id="' . htmlspecialchars($id, ENT_QUOTES) . '"';
        }
        $html .= '>';
        if (is_string($number) && $number !== '') {
            $html .= '<span class="md-h-num" aria-hidden="true">' . htmlspecialchars($number, ENT_QUOTES) . '</span>';
        }
        $html .= '<span class="md-h-content">' . $inner . '</span>';
        if ($id !== '') {
            $html .= '<a class="md-anchor" href="#' . htmlspecialchars($id, ENT_QUOTES)
                . '" aria-label="Link to this section" title="Link to this section">#</a>';
        }

        return $html . '</h' . $level . '>';
    }
}
