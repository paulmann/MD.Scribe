<?php

/**
 * MD.Viewer 3.0 — the Markdown rendering engine.
 *
 * Pipeline (every step is deliberate):
 *
 *   source
 *     → newline/NUL normalisation
 *     → frontmatter extraction (YAML-lite)
 *     → source preprocessing ($$…$$ math + [TOC] → byte-safe tokens)
 *     → glossary table parsing (v2.4 contract)
 *     → league/commonmark (CommonMark + GFM + footnotes + description lists
 *       + attributes + smart punct + ==mark== + MD.Viewer extensions)
 *     → token restore (math blocks, inline TOC)
 *     → HTML sanitisation (DOM allowlist — always on)
 *     → glossary table annotation (stable gd-… cell ids)
 *     → glossary tooltip injection
 *     → metadata (title / eyebrow / description / reading time)
 *     → RenderResult
 *
 * Converters are cached per feature fingerprint (the toggle set changes the
 * registered parsers), so repeated renders in one process reuse the built
 * environment.
 */

declare(strict_types=1);

namespace MdViewer\Markdown;

use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;
use League\CommonMark\Extension\DisallowedRawHtml\DisallowedRawHtmlExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Highlight\HighlightExtension;
use League\CommonMark\Extension\SmartPunct\SmartPunctExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;
use MdViewer\Core\Config;
use MdViewer\Core\FeatureFlags;
use MdViewer\Core\Logger;
use MdViewer\Core\Version;
use MdViewer\Markdown\Extensions\MdViewerExtension;

final class MarkdownEngine
{
    /** @var array<string, MarkdownConverter> per-instance cache — renderers are
     *  bound to THIS engine's ContextHolder, so converters must never be
     *  shared across instances. */
    private array $converters = [];

    private ContextHolder $holder;

    public function __construct(
        private readonly FeatureFlags $flags,
        private readonly Config $config,
        private readonly Logger $logger = new Logger(),
    ) {
        $this->holder = new ContextHolder();
    }

    /**
     * Render a document.
     *
     * @param array{docDir?: string, docPath?: string, urlBuilder?: callable, mediaUrlBuilder?: callable, fileExists?: callable, plainCodeBlocks?: bool, embedMode?: bool} $options
     */
    public function render(string $source, array $options = []): RenderResult
    {
        $source = $this->normalize($source);

        // 1. Frontmatter
        [$frontMatter, $body] = ExcerptService::extractFrontMatter($source);

        // 2. Feature snapshot for this render
        $features = $this->flags->snapshot();
        $autoNumbering = $features['AUTO_NUMBERING'] === true
            && !ExcerptService::hasManualNumbering($body);

        // 3. Preprocessing (math blocks + TOC tokens)
        $mathBlocks = [];
        if (($features['FEATURE_MATH'] ?? true) === true) {
            $processed = SourcePreprocessor::process($body);
            $body = $processed['source'];
            $mathBlocks = $processed['mathBlocks'];
        } else {
            // Still honour [TOC] when math is off.
            $body = (string) preg_replace('/^\s{0,3}\[TOC\]\s*$/im', SourcePreprocessor::TOC_TOKEN, $body);
        }

        // 4. Glossary source pass
        $glossary = ($features['GLOSSARY_TOOLTIPS'] ?? true) === true
            ? GlossaryService::parse($body)
            : [];

        // 5. Context
        $context = new RenderContext();
        $context->docDir = (string) ($options['docDir'] ?? '');
        $context->docPath = (string) ($options['docPath'] ?? '');
        $context->features = $features;
        $context->autoNumbering = $autoNumbering;
        $context->mathBlocks = $mathBlocks;
        $context->urlBuilder = $options['urlBuilder'] ?? null;
        $context->mediaUrlBuilder = $options['mediaUrlBuilder'] ?? null;
        $context->fileExists = $options['fileExists'] ?? null;
        $context->plainCodeBlocks = (bool) ($options['plainCodeBlocks'] ?? false);
        $context->embedMode = (bool) ($options['embedMode'] ?? false);
        $this->holder->current = $context;

        $renderStarted = hrtime(true);
        try {
            // 6. Convert
            $converter = $this->converter($this->fingerprint($features));
            $html = $converter->convert($body)->getContent();

            // 7. Token restore
            if ($mathBlocks !== []) {
                $html = SourcePreprocessor::restore($html, $mathBlocks);
                $context->hasMath = true;
            }
            $html = $this->restoreTocToken($html, $context);

            // 8. Sanitize (always)
            $sanitizer = new HtmlSanitizer(
                allowRawHtml: $this->config->bool('ALLOW_RAW_HTML'),
                allowIframes: $this->config->bool('ALLOW_IFRAMES'),
                allowExternalImages: $this->config->bool('ALLOW_EXTERNAL_IMAGES'),
            );
            $html = $sanitizer->sanitize($html);

            // 9. Glossary annotation + tooltips
            if ($glossary !== []) {
                $html = TableAnnotator::annotate($html);
                $html = GlossaryService::apply($html, $glossary);
            }
        } finally {
            $this->holder->current = null;
            $elapsedMs = (hrtime(true) - $renderStarted) / 1_000_000;
            if ($elapsedMs > 500) {
                $this->logger->info('slow markdown render', ['doc' => $context->docPath, 'ms' => (int) $elapsedMs]);
            }
        }

        // 10. Metadata
        $title = $this->resolveTitle($frontMatter, $body, $context);
        $hero = ExcerptService::heroTitle($title, ($features['SPLIT_TITLE_BY_COLON'] ?? true) === true);
        $description = is_string($frontMatter['description'] ?? null) && $frontMatter['description'] !== ''
            ? (string) $frontMatter['description']
            : ExcerptService::description($body);
        $time = ExcerptService::readingTime($body);

        return new RenderResult(
            html: $html,
            frontMatter: $frontMatter,
            headings: $context->headings,
            tocHtml: $this->buildToc($context->headings, ($features['AUTO_TOC'] ?? true) === true),
            title: $hero['title'],
            eyebrow: $hero['eyebrow'],
            description: $description,
            words: $time['words'],
            readingMinutes: $time['minutes'],
            hasMermaid: $context->hasMermaid,
            hasMath: $context->hasMath,
            hasImages: $context->hasImages,
            glossaryTerms: count($glossary),
        );
    }

    // ── Internals ──────────────────────────────────────────────────────────

    public function normalize(string $source): string
    {
        $source = str_replace(["\r\n", "\r"], "\n", $source);

        return str_replace("\0", '', $source);
    }

    /** Everything that changes the registered parser set. */
    public function fingerprint(?array $features = null): string
    {
        $features ??= $this->flags->snapshot();
        $raw = [
            'v' => Version::VERSION,
            'raw_html' => $this->config->bool('ALLOW_RAW_HTML'),
            'feat' => $features,
        ];

        return substr(hash('sha256', (string) json_encode($raw)), 0, 16);
    }

    private function converter(string $fingerprint): MarkdownConverter
    {
        return $this->converters[$fingerprint] ??= $this->buildConverter();
    }

    private function buildConverter(): MarkdownConverter
    {
        $features = $this->flags->snapshot();
        $allowRaw = $this->config->bool('ALLOW_RAW_HTML');

        $config = [
            'html_input' => $allowRaw ? 'allow' : 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level' => 128,
            'renderer' => [
                'block_separator' => "\n",
                'inner_separator' => "\n",
                'soft_break' => "\n",
            ],
            'footnote' => [
                'container_class' => 'md-footnotes',
                'footnote_class' => 'md-footnote',
                'ref_class' => 'md-footnote-ref',
                'backref_class' => 'md-footnote-backref',
                'backref_symbol' => '↩',
                'container_add_hr' => false,
            ],
            'table' => [
                'wrap' => [
                    'enabled' => true,
                    'tag' => 'div',
                    'attributes' => ['class' => 'md-table-wrap'],
                ],
            ],
        ];

        $environment = new \League\CommonMark\Environment\Environment($config);
        $environment->addExtension(new CommonMarkCoreExtension());

        // GFM — but without the parts we register individually with our own
        // configuration (tables are kept from GFM; strikethrough is configured
        // for single-tilde-off so ~sub~ stays subscript).
        $environment->addExtension(new GithubFlavoredMarkdownExtension());

        if (($features['FEATURE_FOOTNOTES'] ?? true) === true) {
            $environment->addExtension(new FootnoteExtension());
        }
        if (($features['FEATURE_DEFINITION_LISTS'] ?? true) === true) {
            $environment->addExtension(new DescriptionListExtension());
        }
        if (($features['FEATURE_ATTRIBUTES'] ?? true) === true) {
            $environment->addExtension(new AttributesExtension());
        }
        if (($features['FEATURE_HIGHLIGHT_MARK'] ?? true) === true) {
            $environment->addExtension(new HighlightExtension());
        }
        if (($features['FEATURE_SMART_PUNCT'] ?? false) === true) {
            $environment->addExtension(new SmartPunctExtension());
        }
        if ($allowRaw) {
            // Even in raw-HTML mode the truly dangerous tags stay escaped;
            // the sanitizer is the final net.
            $environment->addExtension(new DisallowedRawHtmlExtension());
        }

        $environment->addExtension(new MdViewerExtension($this->holder, $features));

        return new MarkdownConverter($environment);
    }

    /** @param array<string, mixed> $frontMatter */
    private function resolveTitle(array $frontMatter, string $body, RenderContext $context): string
    {
        if (is_string($frontMatter['title'] ?? null) && trim((string) $frontMatter['title']) !== '') {
            return trim((string) $frontMatter['title']);
        }
        foreach ($context->headings as $heading) {
            if ($heading['level'] === 1 && $heading['text'] !== '') {
                return $heading['text'];
            }
        }
        $h1 = ExcerptService::firstH1($body);

        return $h1 ?? ($context->docPath !== '' ? pathinfo($context->docPath, PATHINFO_FILENAME) : 'Document');
    }

    /** Swap @@MDVTOC@@ for the inline table of contents. */
    private function restoreTocToken(string $html, RenderContext $context): string
    {
        if (!str_contains($html, SourcePreprocessor::TOC_TOKEN)) {
            return $html;
        }
        $toc = $this->buildToc($context->headings, true, forPlaceholder: true);
        if ($toc === '') {
            $toc = '<p class="md-toc-empty"><em>This document has no headings yet.</em></p>';
        }
        $html = str_replace('<p>' . SourcePreprocessor::TOC_TOKEN . '</p>', $toc, $html);

        return str_replace(SourcePreprocessor::TOC_TOKEN, $toc, $html);
    }

    /**
     * Build TOC markup from collected headings.
     *
     * Levels are normalized to the distinct set present (h1→h3 documents get
     * a clean 3-level list without gaps), the list is properly nested, and
     * every anchor carries data-toc-anchor for the scrollspy.
     *
     * @param list<array{level:int,text:string,anchor:string,number:?string}> $headings
     */
    public function buildToc(array $headings, bool $enabled, bool $forPlaceholder = false): string
    {
        if (!$enabled || $headings === []) {
            return '';
        }

        // Normalize levels: map the distinct levels present onto 0,1,2,…
        $levels = array_values(array_unique(array_column($headings, 'level')));
        sort($levels);
        $levels = array_slice($levels, 0, 4); // max 4 nested tiers
        $map = array_flip($levels);
        $items = [];
        foreach ($headings as $heading) {
            if (!isset($map[$heading['level']])) {
                continue; // deeper than the 4th distinct tier
            }
            $items[] = ['depth' => $map[$heading['level']]] + $heading;
        }
        if ($items === []) {
            return '';
        }

        $html = '<nav class="md-toc' . ($forPlaceholder ? ' md-toc-inline' : '') . '" aria-label="Table of contents">';
        if ($forPlaceholder) {
            $html .= '<p class="md-toc-heading">' . \MdViewer\View\Icons::svg('list', 'h-4 w-4 mr-1.5 inline-block align-[-2px]') . 'Table of contents</p>';
        }

        $current = null;
        foreach ($items as $item) {
            $depth = (int) $item['depth'];
            if ($current === null) {
                $html .= '<ul class="md-toc-list">';
                $current = $depth;
            } elseif ($depth > $current) {
                for ($l = $current; $l < $depth; $l++) {
                    $html .= '<ul class="md-toc-sublist">';
                }
                $current = $depth;
            } elseif ($depth < $current) {
                for ($l = $current; $l > $depth; $l--) {
                    $html .= '</li></ul>';
                }
                $html .= '</li>';
                $current = $depth;
            } else {
                $html .= '</li>';
            }

            $number = is_string($item['number']) && $item['number'] !== ''
                ? '<span class="md-toc-num">' . htmlspecialchars($item['number']) . '</span>'
                : '';
            $html .= '<li class="md-toc-item md-toc-d' . $depth . '">'
                . '<a href="#' . htmlspecialchars($item['anchor'], ENT_QUOTES) . '"'
                . ' data-toc-anchor="' . htmlspecialchars($item['anchor'], ENT_QUOTES) . '">'
                . $number . '<span class="md-toc-text">' . htmlspecialchars($item['text']) . '</span></a>';
        }
        $html .= '</li>' . str_repeat('</ul>', $current + 1);

        return $html . '</nav>';
    }
}
