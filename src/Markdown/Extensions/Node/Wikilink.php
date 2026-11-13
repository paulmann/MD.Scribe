<?php

/**
 * MD.Scribe 3.3 — inline wikilink node ([[Page]], [[Page|Alias]], [[Page#Heading]]).
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions\Node;

use League\CommonMark\Node\Inline\AbstractInline;

final class Wikilink extends AbstractInline
{
    public function __construct(
        private readonly string $target,
        private readonly ?string $label = null,
        private readonly ?string $anchor = null,
    ) {
        parent::__construct();
    }

    /** Target as written by the author (no extension, no alias, no anchor). */
    public function target(): string
    {
        return $this->target;
    }

    /** Display text when the author wrote `[[Page|Alias]]`. */
    public function label(): ?string
    {
        return $this->label;
    }

    /** Heading the link points at, when the author wrote `#Heading`. */
    public function anchor(): ?string
    {
        return $this->anchor;
    }
}
