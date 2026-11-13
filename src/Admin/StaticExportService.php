<?php

/**
 * MD.Scribe 3.3 — static site export.
 *
 * Renders every document to a standalone HTML file and packs the tree into a
 * ZIP under storage/exports/. Pages are self-contained: one small stylesheet,
 * no scripts, no CDN, so the archive opens from a file:// path and can be
 * hosted on any static host.
 *
 * Deliberate limits (documented in docs/admin-guide.md): Mermaid diagrams and
 * KaTeX math stay as their source because rendering them needs JavaScript;
 * links to documents become relative .html links, and everything else points
 * at the exported index.
 */

declare(strict_types=1);

namespace MdViewer\Admin;

use MdViewer\Core\Config;
use MdViewer\Core\Logger;
use MdViewer\Core\Paths;
use MdViewer\Core\Version;
use MdViewer\Files\MarkdownRepository;
use MdViewer\Markdown\MarkdownEngine;
use ZipArchive;

final class StaticExportService
{
    /** Content assets copied into the archive (images, PDFs, …). */
    private const int MAX_MEDIA_BYTES = 200 * 1024 * 1024;

    private const int MAX_MEDIA_FILES = 5000;

    private const array SKIP_DIRS = [
        'node_modules', 'vendor', 'md.backup', 'storage', '.git', '.svn', '.hg',
        '.idea', '.vscode', '.next', '.cache', 'dist', 'build',
    ];

    /** @var list<string> HTML paths written during the current export */
    private array $written = [];

    public function __construct(
        private readonly MarkdownRepository $repository,
        private readonly MarkdownEngine $engine,
        private readonly Config $config,
        private readonly Logger $logger = new Logger(),
        private readonly string $outputDir = '',
    ) {
    }

    /**
     * Export the whole library and return the archive name.
     *
     * @return array{ok: bool, error?: string, file?: string, path?: string, bytes?: int, documents?: int, media?: int, errors?: list<string>}
     */
    public function export(?string $name = null): array
    {
        if (!class_exists(ZipArchive::class)) {
            return ['ok' => false, 'error' => 'The zip extension is not available on this host.'];
        }

        $documents = $this->repository->scan();
        if ($documents === []) {
            return ['ok' => false, 'error' => 'No documents to export.'];
        }

        $temp = $this->tempDir();
        if (!is_dir($temp) && !@mkdir($temp, 0o755, true) && !is_dir($temp)) {
            return ['ok' => false, 'error' => 'Cannot create a temporary export directory.'];
        }

        $errors = [];
        $this->written = [];
        try {
            $this->writeStylesheet($temp);
            $rendered = 0;
            foreach ($documents as $document) {
                $relative = (string) $document['path'];
                try {
                    if ($this->renderDocument($relative, $temp)) {
                        $rendered++;
                    }
                } catch (\Throwable $e) {
                    $errors[] = $relative . ': ' . $e->getMessage();
                    $this->logger->warning('static export: document failed', [
                        'path' => $relative,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            $media = $this->copyMedia($temp);

            // A document named index.md must not be overwritten by the
            // generated listing: the listing always lands in all-documents.html
            // and only doubles as index.html when no document owns that name.
            /** @var list<string> $written */
            $written = $this->written;
            $this->writeIndex($temp, $documents, !in_array('index.html', $written, true));

            $file = $this->archiveName($name);
            $path = $this->outputDir() . '/' . $file;
            if (!is_dir(dirname($path)) && !@mkdir(dirname($path), 0o755, true) && !is_dir(dirname($path))) {
                return ['ok' => false, 'error' => 'Cannot create storage/exports.'];
            }
            if (!$this->zip($temp, $path)) {
                return ['ok' => false, 'error' => 'Cannot write the ZIP archive.'];
            }

            $bytes = (int) @filesize($path);
            $this->logger->info('static export written', [
                'file' => $file,
                'documents' => $rendered,
                'media' => $media,
                'bytes' => $bytes,
            ]);

            return [
                'ok' => true,
                'file' => $file,
                'path' => $path,
                'bytes' => $bytes,
                'documents' => $rendered,
                'media' => $media,
                'errors' => $errors,
            ];
        } finally {
            $this->removeTree($temp);
        }
    }

    /** Absolute path of one exported archive, or null when the name is not ours. */
    public function archivePath(string $file): ?string
    {
        $file = basename(trim($file));
        if (preg_match('/^[A-Za-z0-9._-]+\.zip$/', $file) !== 1) {
            return null;
        }
        $path = $this->outputDir() . '/' . $file;

        return is_file($path) ? $path : null;
    }

    // ── Rendering ──────────────────────────────────────────────────────────

    private function renderDocument(string $relative, string $temp): bool
    {
        $absolute = $this->repository->resolve($relative);
        if ($absolute === null) {
            return false;
        }
        $source = MarkdownRepository::read($absolute);
        if ($source === null) {
            return false;
        }

        $docDir = dirname($relative) === '.' ? '' : dirname($relative);
        $depth = $docDir === '' ? 0 : substr_count($docDir, '/') + 1;
        $base = str_repeat('../', $depth);

        $result = $this->engine->render($source, [
            'docDir' => $docDir,
            'docPath' => $relative,
            'plainCodeBlocks' => true,
            'embedMode' => true,
            'urlBuilder' => fn (array $params): string => $this->staticUrl($params, $base, $relative),
            'mediaUrlBuilder' => fn (string $rel): string => $base . self::encodePath(ltrim($rel, '/')),
            'fileExists' => fn (string $rel): bool => $this->repository->resolve($rel) !== null,
        ]);

        $target = $temp . '/' . self::htmlPath($relative);
        $dir = dirname($target);
        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            throw new \RuntimeException('cannot create directory');
        }

        $title = (string) ($result->title ?? pathinfo($relative, PATHINFO_FILENAME));
        $html = $this->page($title, $result->html, $base);
        $relativeHtml = self::htmlPath($relative);
        $this->written[] = $relativeHtml;

        return @file_put_contents($target, $html, LOCK_EX) !== false;
    }

    /**
     * @param array<string, scalar|null> $params
     */
    private function staticUrl(array $params, string $base, string $current): string
    {
        $file = isset($params['file']) ? (string) $params['file'] : '';
        if ($file !== '') {
            if (str_ends_with(strtolower($file), '.md')) {
                return $base . self::encodePath(substr($file, 0, -3) . '.html');
            }

            // Not a document (image download, anchor on a directory listing).
            return $base . self::encodePath($file);
        }

        $media = isset($params['media']) ? (string) $params['media'] : '';
        if ($media !== '') {
            return $base . self::encodePath(ltrim($media, '/'));
        }

        return $base . 'index.html';
    }

    private function page(string $title, string $body, string $base): string
    {
        $site = htmlspecialchars($this->config->str('SITE_NAME'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $titleText = htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $version = Version::VERSION;

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$titleText} — {$site}</title>
<link rel="stylesheet" href="{$base}assets/md-scribe-export.css">
</head>
<body>
<header class="export-header"><a href="{$base}index.html">{$site}</a></header>
<main class="md-body">
{$body}
</main>
<footer class="export-footer">Exported from MD.Scribe {$version} · <a href="{$base}all-documents.html">All documents</a></footer>
</body>
</html>
HTML;
    }

    /** @param list<array{path: string, title: string}> $documents */
    private function writeIndex(string $temp, array $documents, bool $alsoAsIndex): void
    {
        $site = htmlspecialchars($this->config->str('SITE_NAME'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $items = '';
        foreach ($documents as $document) {
            $path = (string) $document['path'];
            $title = htmlspecialchars((string) $document['title'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $href = htmlspecialchars(self::encodePath(substr($path, 0, -3) . '.html'), ENT_QUOTES);
            $items .= '<li><a href="' . $href . '">' . $title . '</a>'
                . ' <span class="export-path">' . htmlspecialchars($path, ENT_QUOTES) . '</span></li>' . "\n";
        }

        $html = <<<HTML
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$site}</title>
<link rel="stylesheet" href="assets/md-scribe-export.css">
</head>
<body>
<header class="export-header">{$site}</header>
<main class="md-body">
<h1>{$site}</h1>
<p class="export-note">Static export of every document in the library.</p>
<ul class="export-index">
{$items}</ul>
</main>
<footer class="export-footer">Exported from MD.Scribe</footer>
</body>
</html>
HTML;
        @file_put_contents($temp . '/all-documents.html', $html, LOCK_EX);
        if ($alsoAsIndex) {
            @file_put_contents($temp . '/index.html', $html, LOCK_EX);
        }
    }

    private function writeStylesheet(string $temp): void
    {
        $assets = $temp . '/assets';
        if (!is_dir($assets) && !@mkdir($assets, 0o755, true) && !is_dir($assets)) {
            throw new \RuntimeException('cannot create the assets directory');
        }

        $css = <<<'CSS'
:root { color-scheme: light dark; }
* { box-sizing: border-box; }
body { margin: 0; font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; line-height: 1.65; }
.export-header { padding: 0.75rem 1.25rem; border-bottom: 1px solid color-mix(in srgb, currentColor 15%, transparent); font-weight: 600; }
.export-header a { color: inherit; text-decoration: none; }
.export-footer { padding: 1.5rem 1.25rem; font-size: 0.8rem; opacity: 0.7; }
.md-body { max-width: 52rem; margin: 0 auto; padding: 2rem 1.25rem 3rem; }
.md-body h1, .md-body h2, .md-body h3 { line-height: 1.25; margin: 1.8em 0 0.6em; }
.md-body h1:first-child { margin-top: 0; }
.md-body a { color: #0b62c4; }
.md-body code, .md-body pre { font-family: ui-monospace, SFMono-Regular, Consolas, monospace; font-size: 0.9em; }
.md-body code { background: color-mix(in srgb, currentColor 8%, transparent); padding: 0.1em 0.35em; border-radius: 4px; }
.md-body pre { background: color-mix(in srgb, currentColor 7%, transparent); padding: 1em; border-radius: 8px; overflow-x: auto; }
.md-body pre code { background: none; padding: 0; }
.md-body table { border-collapse: collapse; width: 100%; margin: 1.2em 0; }
.md-body th, .md-body td { border: 1px solid color-mix(in srgb, currentColor 20%, transparent); padding: 0.45em 0.7em; text-align: left; }
.md-body blockquote { margin: 1.2em 0; padding: 0.2em 1em; border-left: 3px solid color-mix(in srgb, currentColor 30%, transparent); }
.md-body img { max-width: 100%; height: auto; }
.md-wikilink-missing { color: #b45309; text-decoration: underline wavy; }
.md-wikilink-refused { text-decoration: line-through; opacity: 0.7; }
.md-alert { margin: 1.2em 0; padding: 0.8em 1em; border-left: 4px solid currentColor; border-radius: 6px; }
.export-index { padding-left: 1.2em; }
.export-index li { margin: 0.35em 0; }
.export-path { font-size: 0.78em; opacity: 0.6; margin-left: 0.4em; }
.export-note { opacity: 0.75; }
CSS;

        @file_put_contents($assets . '/md-scribe-export.css', $css . "\n", LOCK_EX);
    }

    // ── Assets and archive ─────────────────────────────────────────────────

    /** Copy non-Markdown files from the browse root, bounded. */
    private function copyMedia(string $temp): int
    {
        $root = $this->repository->browseDir();
        if (!is_dir($root)) {
            return 0;
        }

        $copied = 0;
        $bytes = 0;
        $stack = [[$root, '']];
        while ($stack !== []) {
            [$dir, $rel] = array_pop($stack);
            $entries = @scandir($dir);
            if ($entries === false) {
                continue;
            }
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..' || str_starts_with($entry, '.')) {
                    continue;
                }
                $full = $dir . DIRECTORY_SEPARATOR . $entry;
                $relative = $rel === '' ? $entry : $rel . '/' . $entry;
                if (is_dir($full)) {
                    if (!in_array($entry, self::SKIP_DIRS, true)) {
                        $stack[] = [$full, $relative];
                    }
                    continue;
                }
                if (str_ends_with(strtolower($entry), '.md')) {
                    continue;
                }
                $size = (int) @filesize($full);
                if ($copied >= self::MAX_MEDIA_FILES || $bytes + $size > self::MAX_MEDIA_BYTES) {
                    $this->logger->warning('static export: media cap reached', ['files' => $copied, 'bytes' => $bytes]);

                    return $copied;
                }
                $target = $temp . '/' . $relative;
                $targetDir = dirname($target);
                if (!is_dir($targetDir) && !@mkdir($targetDir, 0o755, true) && !is_dir($targetDir)) {
                    continue;
                }
                if (@copy($full, $target)) {
                    $copied++;
                    $bytes += $size;
                }
            }
        }

        return $copied;
    }

    private function zip(string $source, string $target): bool
    {
        $zip = new ZipArchive();
        if ($zip->open($target, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST,
        );
        $source = rtrim(str_replace('\\', '/', $source), '/') . '/';
        foreach ($iterator as $item) {
            /** @var \SplFileInfo $item */
            $path = str_replace('\\', '/', $item->getPathname());
            $local = substr($path, strlen($source));
            if ($item->isDir()) {
                $zip->addEmptyDir($local);
            } else {
                $zip->addFile($item->getPathname(), $local);
            }
        }

        return $zip->close();
    }

    private function archiveName(?string $name): string
    {
        $slug = $name !== null ? preg_replace('/[^A-Za-z0-9._-]+/', '-', $name) : null;
        $slug = is_string($slug) ? trim((string) $slug, '-.') : '';

        return ($slug !== '' ? $slug : 'site') . '-' . date('Ymd-His') . '.zip';
    }

    private function outputDir(): string
    {
        $dir = $this->outputDir !== '' ? $this->outputDir : Paths::exports();

        return rtrim(str_replace('\\', '/', $dir), '/');
    }

    private function tempDir(): string
    {
        return rtrim(str_replace('\\', '/', sys_get_temp_dir()), '/') . '/mdscribe-export-' . bin2hex(random_bytes(6));
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            /** @var \SplFileInfo $item */
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }

    private static function htmlPath(string $relative): string
    {
        return preg_replace('/\.md$/i', '.html', $relative) ?? ($relative . '.html');
    }

    private static function encodePath(string $path): string
    {
        $segments = array_map(
            static fn (string $segment): string => rawurlencode($segment),
            explode('/', str_replace('\\', '/', $path)),
        );

        return implode('/', $segments);
    }
}
