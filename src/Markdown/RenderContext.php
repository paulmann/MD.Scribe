<?php

/**
 * MD.Viewer 3.0 — per-render state shared between the engine, its listeners
 * and its node renderers (PHP is single-threaded per request, so a holder
 * with the "current" context is safe and keeps renderers stateless).
 */

declare(strict_types=1);

namespace MdViewer\Markdown;

final class RenderContext
{
    /** Relative directory of the document inside the browse root ('' = root). */
    public string $docDir = '';

    /** Relative path of the document ('' = clipboard preview / no file). */
    public string $docPath = '';

    /** @var list<array{level: int, text: string, anchor: string, number: ?string}> */
    public array $headings = [];

    /** Rendered tables counter (glossary slugs mirror document order). */
    public int $tableCount = 0;

    /** @var list<string> extracted $$…$$ blocks, replaced by tokens before parsing */
    public array $mathBlocks = [];

    /** True when the output contains a mermaid diagram. */
    public bool $hasMermaid = false;

    /** True when the output contains math elements. */
    public bool $hasMath = false;

    /** True when at least one image was rendered. */
    public bool $hasImages = false;

    /** @var array<string, bool|string> feature snapshot for the current render */
    public array $features = [];

    /** Auto-numbering allowed for this document (false when the author numbers manually). */
    public bool $autoNumbering = true;

    /** @var (callable(array<string, string|int|bool|null>): string)|null */
    public $urlBuilder = null;

    /** @var (callable(string): string)|null */
    public $mediaUrlBuilder = null;

    /** @var (callable(string): bool)|null existence checker for link health */
    public $fileExists = null;

    public function fileExists(string $relative): ?bool
    {
        return is_callable($this->fileExists) ? (bool) ($this->fileExists)($relative) : null;
    }

    public function feature(string $key, bool $default = true): bool
    {
        return (bool) ($this->features[$key] ?? $default);
    }

    public function url(array $params = []): string
    {
        if (is_callable($this->urlBuilder)) {
            return (string) ($this->urlBuilder)($params);
        }

        return '?' . http_build_query($params);
    }

    public function mediaUrl(string $relative): string
    {
        if (is_callable($this->mediaUrlBuilder)) {
            return (string) ($this->mediaUrlBuilder)($relative);
        }

        return $relative;
    }
}
