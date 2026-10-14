<?php

/**
 * MD.Viewer 3.0 — inline math parser: $…$ → KaTeX span.
 *
 * Guard rails (the currency problem): the content must not start or end with
 * whitespace, must not contain another unescaped $ or a newline, and the
 * closing $ must not be followed by a digit — so "$5 and $10" stays prose
 * while "$E=mc^2$" becomes math. \$ escapes are respected.
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use MdViewer\Markdown\ContextHolder;
use MdViewer\Markdown\Extensions\Node\MathInline;

final class MathInlineParser implements InlineParserInterface
{
    public function __construct(private readonly ContextHolder $holder)
    {
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\$(?!\s)((?:\\\\\$|[^\$\n])+?)(?<!\s)\$(?!\d)');
    }

    public function parse(InlineParserContext $context): bool
    {
        $match = $context->getFullMatch();
        $tex = str_replace('\\$', '$', substr($match, 1, -1));
        if (trim($tex) === '') {
            return false;
        }

        $context->getCursor()->advanceBy($context->getFullMatchLength());
        $context->getContainer()->appendChild(new MathInline($tex));
        $ctx = $this->holder->current;
        if ($ctx !== null) {
            $ctx->hasMath = true;
        }

        return true;
    }
}
