<?php

/**
 * MD.Scribe 3.2 — machine render service: one Markdown/HTML/plain-text input in,
 * one safe HTML fragment out, no state on disk.
 *
 * Used by the machine API (`POST /index.php?api=render` with `X-Api-Key`), not
 * by the viewer: the viewer renders inside a request lifecycle with a session,
 * theme and search index, while this pipeline is deliberately pure.
 *
 * Contract:
 *  - `markdown` → `MdViewer\Standalone\Renderer` in embed mode (the same
 *    pipeline as `bin/md-scribe-render` without `--viewer-mode`): portable
 *    `<pre><code class="hljs …">` blocks, no toolbars, no numbering, no TOC;
 *  - `html` → `MdViewer\Markdown\HtmlSanitizer` with its strict allowlist
 *    (`<script>`, `on*` handlers, `javascript:` URLs are removed);
 *  - `text` → `<div class="md-text">` + `htmlspecialchars()`: the text is shown
 *    verbatim but WRAPS. A `<pre>` was wrong for stored answers — it does not
 *    break long lines, so a paragraph turned into a horizontal scrollbar
 *    (portal request, 29.10.2026);
 *  - `auto` → HTML when the content starts with a known block tag, Markdown
 *    when any explicit Markdown signal is present (ties go to Markdown),
 *    plain text otherwise.
 */

declare(strict_types=1);

namespace MdViewer\Api;

use MdViewer\Markdown\HtmlSanitizer;
use MdViewer\Standalone\Renderer;

final class RenderService
{
    /** Hard input limit of the machine API (bytes, UTF-8). */
    public const int MAX_CONTENT_BYTES = 262144;

    /** Accepted `format` values; `auto` is the default. */
    public const array FORMATS = ['auto', 'markdown', 'html', 'text'];

    /**
     * Tags that make an input "HTML" during auto-detection. Deliberately a
     * block-level allowlist: `<p>`, `<div>` and friends mean the caller sent
     * markup, while a stray `<` in prose does not.
     */
    public const array HTML_BLOCK_TAGS = [
        'p', 'div', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'table',
        'pre', 'blockquote', 'section', 'article', 'details', 'hr', 'br', 'img',
        'a', 'span', 'code', 'strong', 'em',
    ];

    private ?Renderer $renderer;
    private ?HtmlSanitizer $sanitizer;

    /**
     * Both collaborators are lazy: a `text`/`html` request must not pay for the
     * CommonMark pipeline, and tests may inject doubles.
     */
    public function __construct(?Renderer $renderer = null, ?HtmlSanitizer $sanitizer = null)
    {
        $this->renderer = $renderer;
        $this->sanitizer = $sanitizer;
    }

    /**
     * Validate, detect the format and render one fragment.
     *
     * @param string $content         raw payload (already decoded JSON)
     * @param string $requestedFormat one of {@see self::FORMATS}
     *
     * @throws RenderError empty_content, bad_format, too_large
     */
    public function process(string $content, string $requestedFormat = 'auto'): RenderedFragment
    {
        $requested = strtolower(trim($requestedFormat));
        if ($requested === '') {
            throw RenderError::badFormat('');
        }
        if (!in_array($requested, self::FORMATS, true)) {
            throw RenderError::badFormat($requestedFormat);
        }

        $bytes = strlen($content);
        if ($bytes > self::MAX_CONTENT_BYTES) {
            throw RenderError::tooLarge($bytes);
        }
        if (trim($content) === '') {
            throw RenderError::emptyContent();
        }

        $format = $requested === 'auto' ? self::detectFormat($content) : $requested;

        $html = match ($format) {
            'markdown' => $this->renderMarkdown($content),
            'html' => $this->sanitizeHtml($content),
            default => $this->renderText($content),
        };

        return new RenderedFragment($format, $html);
    }

    /** One of `html`, `markdown`, `text` — never `auto`. */
    public static function detectFormat(string $content): string
    {
        if (self::looksLikeHtml($content)) {
            return 'html';
        }

        return self::hasMarkdownSignal($content) ? 'markdown' : 'text';
    }

    /**
     * HTML when the first tag after leading whitespace is on the block
     * allowlist (a closing tag counts, `<!doctype>`/`<?php`/comments do not).
     */
    public static function looksLikeHtml(string $content): bool
    {
        $trimmed = ltrim($content);
        if ($trimmed === '' || $trimmed[0] !== '<') {
            return false;
        }
        if (preg_match('/^<\s*\/?\s*([a-zA-Z][a-zA-Z0-9]*)\b/', $trimmed, $m) !== 1) {
            return false;
        }

        return in_array(strtolower($m[1]), self::HTML_BLOCK_TAGS, true);
    }

    /**
     * True when at least one explicit Markdown construct is present: ATX
     * heading, list item, fenced code block, blockquote, table row/separator,
     * horizontal rule, bold span, link or image.
     */
    public static function hasMarkdownSignal(string $content): bool
    {
        $lines = preg_split('/\R/', $content) ?: [];
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if ($trimmed === '') {
                continue;
            }
            if (preg_match('/^#{1,6}(\s|$)/', $trimmed) === 1) {
                return true; // ATX heading
            }
            if (preg_match('/^([-*+]|\d{1,9}[.)])\s/', $trimmed) === 1) {
                return true; // list item
            }
            if (str_starts_with($trimmed, '```') || str_starts_with($trimmed, '~~~')) {
                return true; // fenced code block
            }
            if (str_starts_with($trimmed, '>')) {
                return true; // blockquote
            }
            if (str_starts_with($trimmed, '|') && str_ends_with(rtrim($trimmed), '|')) {
                return true; // table row
            }
            if (preg_match('/^\|?[\s:|-]*-{3,}[\s:|-]*$/', $trimmed) === 1) {
                return true; // table separator or horizontal rule
            }
            if (preg_match('/\*\*[^*\n]+\*\*/', $line) === 1
                || preg_match('/!?\[[^\]\n]+\]\([^)\s]+\)/', $line) === 1) {
                return true; // bold, link or image
            }
        }

        return false;
    }

    /** Markdown → HTML fragment through the standalone renderer's embed mode. */
    private function renderMarkdown(string $content): string
    {
        return $this->renderer()->renderString($content, [
            'plainCodeBlocks' => true,
            'embedMode' => true,
        ])->html;
    }

    /** Caller-supplied HTML → allowlist-sanitised fragment. */
    private function sanitizeHtml(string $content): string
    {
        return $this->sanitizer()->sanitize($content);
    }

    /**
     * Plain text → escaped block that WRAPS.
     *
     * `<pre>` shows the text verbatim but never wraps (pre-wrap is a CSS decision
     * a separate consumer may not make), so a long stored answer produced a
     * horizontal scrollbar. The inline style keeps the wrapping behaviour wherever
     * the fragment lands — a portal modal, an HTML e-mail — without asking every
     * consumer to ship a stylesheet.
     */
    private function renderText(string $content): string
    {
        return '<div class="md-text" style="white-space:pre-wrap;overflow-wrap:anywhere">'
            . htmlspecialchars($content, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
            . '</div>';
    }

    private function renderer(): Renderer
    {
        return $this->renderer ??= new Renderer();
    }

    private function sanitizer(): HtmlSanitizer
    {
        return $this->sanitizer ??= new HtmlSanitizer();
    }
}
