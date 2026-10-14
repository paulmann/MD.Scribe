<?php

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use MdViewer\Markdown\Extensions\Node\SupInline;

final class SupInlineRenderer implements NodeRendererInterface
{
    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if (!$node instanceof SupInline) {
            return '';
        }

        return '<sup>' . htmlspecialchars($node->content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</sup>';
    }
}
