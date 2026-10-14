<?php

/**
 * MD.Viewer 3.0 — superscript parser: ^x^ → <sup>.
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use MdViewer\Markdown\Extensions\Node\SupInline;

final class SupInlineParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('(?<!\^)\^([^\s^]+)\^(?!\^)')->caseSensitive();
    }

    public function parse(InlineParserContext $context): bool
    {
        $matches = $context->getMatches();
        $context->getCursor()->advanceBy($context->getFullMatchLength());
        $context->getContainer()->appendChild(new SupInline((string) ($matches[1] ?? '')));

        return true;
    }
}
