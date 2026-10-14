<?php

/**
 * MD.Viewer 3.0 — inline math node ($…$).
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions\Node;

use League\CommonMark\Node\Inline\AbstractInline;

final class MathInline extends AbstractInline
{
    public function __construct(private readonly string $tex)
    {
        parent::__construct();
    }

    public function tex(): string
    {
        return $this->tex;
    }
}
