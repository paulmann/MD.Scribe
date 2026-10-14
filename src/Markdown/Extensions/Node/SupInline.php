<?php

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions\Node;

use League\CommonMark\Node\Inline\AbstractInline;

final class SupInline extends AbstractInline
{
    public function __construct(public readonly string $content)
    {
        parent::__construct();
    }
}
