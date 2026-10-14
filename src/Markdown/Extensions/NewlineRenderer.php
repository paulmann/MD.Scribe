<?php

/**
 * MD.Viewer 3.0 — newline renderer honouring the legacy MD.Viewer rule:
 * with DOUBLE_LINE_BREAKS a single newline inside a paragraph becomes <br>
 * (v2 default), otherwise classic CommonMark soft breaks.
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use MdViewer\Markdown\ContextHolder;

final class NewlineRenderer implements NodeRendererInterface
{
    public function __construct(private readonly ContextHolder $holder)
    {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (!$node instanceof Newline) {
            return '';
        }

        if ($node->getType() === Newline::HARDBREAK) {
            return "<br />\n";
        }

        $doubleBreaks = $this->holder->current?->feature('DOUBLE_LINE_BREAKS', true) ?? true;

        return $doubleBreaks ? "<br />\n" : "\n";
    }
}
