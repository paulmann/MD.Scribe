<?php

/**
 * MD.Scribe 3.2 — one machine-rendered fragment: the detected (or requested)
 * format plus the safe HTML fragment.
 *
 * The HTML is always a fragment — no `<!DOCTYPE>`, `<html>`, `<head>` or
 * `<body>`, and no viewer chrome — so the caller can drop it into an existing
 * page. Every branch of the pipeline is sanitised.
 */

declare(strict_types=1);

namespace MdViewer\Api;

final readonly class RenderedFragment
{
    public function __construct(
        public string $format,
        public string $html,
    ) {
    }
}
