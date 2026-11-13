<?php

/**
 * MD.Scribe 3.3 — inline wikilink parser: [[…]] → Wikilink node.
 *
 * The inner text is split into target, optional `|label` and optional
 * `#anchor`. Empty or refused links return false *without consuming input*, so
 * the text stays literal and the regular link parser still sees it.
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use MdViewer\Files\WikilinkResolver;
use MdViewer\Markdown\Extensions\Node\Wikilink;

final class WikilinkParser implements InlineParserInterface
{
    /**
     * Highest inline priority in the engine, so `[[` is claimed before the
     * CommonMark link parser looks at the same bracket.
     */
    public const int PRIORITY = 70;

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\[\[([^\[\]\n]{1,512})\]\]');
    }

    public function parse(InlineParserContext $context): bool
    {
        $inner = substr($context->getFullMatch(), 2, -2);
        $parts = WikilinkResolver::split($inner);
        if ($parts === null) {
            return false;
        }

        $context->getCursor()->advanceBy($context->getFullMatchLength());
        $context->getContainer()->appendChild(
            new Wikilink($parts['target'], $parts['label'], $parts['anchor']),
        );

        return true;
    }
}
