<?php

/**
 * MD.Scribe 3.x — framework-free renderer for embedding Markdown elsewhere.
 *
 * The application normally renders documents inside its own request lifecycle
 * (session, CSP nonces, theme cookies, admin flags). Many integrations only
 * need the pipeline: CommonMark/GFM + MD.Scribe extensions + sanitizer →
 * HTML. This class exposes exactly that, with no front controller, no session
 * and no output buffering:
 *
 *     require '/path/to/MD.Scribe/src/bootstrap.php';
 *     $renderer = new \MdViewer\Standalone\Renderer();
 *     echo $renderer->renderFile('/docs/README.md')->html;
 *
 * Embed mode (the default here) additionally:
 *  - renders fenced code blocks as portable `<pre><code class="hljs …">`
 *    without toolbars/copy buttons (see RenderContext::$plainCodeBlocks), so
 *    an external stylesheet such as github-markdown-css can style them;
 *  - turns off automatic heading numbering and the auto-TOC, matching how a
 *    README is presented on GitHub.
 *
 * Everything else — relative-link rewriting, GitHub alerts, tables, task
 * lists, footnotes, math, mermaid, sanitisation — behaves exactly as in the
 * viewer.
 */

declare(strict_types=1);

namespace MdViewer\Standalone;

use MdViewer\Core\Config;
use MdViewer\Core\FeatureFlags;
use MdViewer\Core\Logger;
use MdViewer\Markdown\MarkdownEngine;
use MdViewer\Markdown\RenderResult;

final class Renderer
{
    /** Cookie-layer overrides applied in embed mode (see FeatureFlags::resolve). */
    private const array EMBED_FEATURES = [
        'mdv_feat_AUTO_NUMBERING' => '0',
        'mdv_feat_AUTO_TOC' => '0',
        'mdv_feat_GLOSSARY_TOOLTIPS' => '0',
        'mdv_feat_FEATURE_DOWNLOAD_BUTTON' => '0',
        'mdv_feat_FEATURE_DOWNLOAD_ICON' => '0',
        'mdv_feat_FEATURE_COPY_BUTTON' => '0',
    ];

    private readonly Config $config;
    private readonly MarkdownEngine $engine;

    /**
     * @param string|null          $iniPath          `.md.ini` to load; null = the project's own config
     * @param array<string,string> $featureOverrides extra `mdv_feat_*` cookie values ('' disables)
     */
    public function __construct(?string $iniPath = null, array $featureOverrides = [])
    {
        $this->config = $iniPath !== null ? Config::loadFrom($iniPath) : Config::instance();
        $flags = new FeatureFlags($this->config, array_merge(self::EMBED_FEATURES, $featureOverrides));
        $this->engine = new MarkdownEngine($flags, $this->config, new Logger($this->config));
    }

    /**
     * Render one Markdown file from disk.
     *
     * Options are the MarkdownEngine options (`docDir`, `docPath`, `urlBuilder`,
     * `mediaUrlBuilder`, `fileExists`) plus `plainCodeBlocks` (default true here).
     *
     * @param array<string,mixed> $options
     */
    public function renderFile(string $path, array $options = []): RenderResult
    {
        $source = @file_get_contents($path);
        if ($source === false) {
            throw new \RuntimeException('Cannot read Markdown file: ' . $path);
        }

        $options['docPath'] ??= basename($path);
        $options['plainCodeBlocks'] ??= true;
        $options['embedMode'] ??= true;

        return $this->engine->render($source, $options);
    }

    /**
     * Render a Markdown string (clipboard previews, API input, tests).
     *
     * @param array<string,mixed> $options
     */
    public function renderString(string $source, array $options = []): RenderResult
    {
        $options['plainCodeBlocks'] ??= true;
        $options['embedMode'] ??= true;

        return $this->engine->render($source, $options);
    }

    /**
     * Convenience wrapper for README-style rendering: resolves relative links
     * and media against $baseUrl (for example `https://repo.example.com/`),
     * and marks links to files that do not exist under $docRoot.
     *
     * @param array<string,mixed> $options
     */
    public function renderFileWithBase(
        string $path,
        string $baseUrl,
        ?string $docRoot = null,
        array $options = [],
    ): RenderResult {
        $base = rtrim($baseUrl, '/') . '/';
        $root = $docRoot ?? dirname((string) realpath($path));
        $root = rtrim($root, '/');

        $options['urlBuilder'] ??= static function (array $params) use ($base): string {
            $file = (string) ($params['file'] ?? '');

            return $base . ltrim($file, '/');
        };
        $options['mediaUrlBuilder'] ??= static fn (string $rel): string => $base . ltrim($rel, '/');
        $options['fileExists'] ??= static function (string $rel) use ($root): bool {
            return is_file($root . '/' . ltrim($rel, '/'));
        };

        return $this->renderFile($path, $options);
    }

    public function config(): Config
    {
        return $this->config;
    }
}
