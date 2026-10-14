<?php

/**
 * MD.Viewer 3.0 — holder for the render context currently in flight.
 * Injected into every custom parser/renderer/listener of MdViewerExtension.
 */

declare(strict_types=1);

namespace MdViewer\Markdown;

final class ContextHolder
{
    public ?RenderContext $current = null;

    public function get(): RenderContext
    {
        if ($this->current === null) {
            throw new \LogicException('No render context in flight');
        }

        return $this->current;
    }
}
