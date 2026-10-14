<?php

/**
 * MD.Viewer 3.0 — inline code renderer with the v2 "universal patterns"
 * behaviour: a code span that reads like a node/relation chain renders as a
 * visual chain; everything else stays a plain <code>.
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use MdViewer\Markdown\ContextHolder;
use MdViewer\Markdown\UniversalPattern;

final class CodeSpanRenderer implements NodeRendererInterface
{
    public function __construct(private readonly ContextHolder $holder)
    {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (!$node instanceof Code) {
            return '';
        }

        $literal = $node->getLiteral();

        if ($this->holder->current?->feature('UNIVERSAL_PATTERNS', true) === true) {
            $elements = UniversalPattern::parse($literal);
            if ($elements !== null) {
                return UniversalPattern::render($elements);
            }
        }

        return '<code class="md-code-inline">' . htmlspecialchars($literal, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</code>';
    }
}
