<?php

/**
 * MD.Viewer 3.0 — emoji shortcode parser: :smile: → 😊
 *
 * Backed by the full GitHub gemoji database (1900+ aliases, generated map).
 * Unknown shortcodes return false so the character is treated as plain text
 * and other parsers (autolinks etc.) still get their chance — "12:30:45"
 * or "http://host:8080:9090" pass through as prose.
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use MdViewer\Markdown\EmojiMap;

final class EmojiInlineParser implements InlineParserInterface
{
    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex(':([a-z0-9_+\-]{1,40}):');
    }

    public function parse(InlineParserContext $context): bool
    {
        $matches = $context->getMatches();
        $code = strtolower((string) ($matches[1] ?? ''));
        $emoji = EmojiMap::all()[$code] ?? null;
        if ($emoji === null) {
            return false;
        }

        $context->getCursor()->advanceBy($context->getFullMatchLength());
        $context->getContainer()->appendChild(new Text($emoji));

        return true;
    }
}
