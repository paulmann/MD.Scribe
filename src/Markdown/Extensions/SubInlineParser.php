<?php

/**
 * MD.Viewer 3.0 — subscript parser: ~x~ → <sub>.
 *
 * The lookarounds keep `~~strikethrough~~` (GFM, incl. the single-tilde
 * variant the league delimiter processor accepts) untouched: a tilde run is
 * never split by this parser.
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use MdViewer\Markdown\Extensions\Node\SubInline;

final class SubInlineParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('(?<!~)~([^\s~]+)~(?!~)')->caseSensitive();
    }

    public function parse(InlineParserContext $context): bool
    {
        $matches = $context->getMatches();
        $context->getCursor()->advanceBy($context->getFullMatchLength());
        $context->getContainer()->appendChild(new SubInline((string) ($matches[1] ?? '')));

        return true;
    }
}
