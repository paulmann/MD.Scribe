<?php

/**
 * MD.Viewer 3.0 — document viewer controller.
 *
 * Resolves and renders a document (or the configured home document), with:
 *  - the full render cache (source hash × feature fingerprint);
 *  - HTTP caching (ETag + Last-Modified + 304);
 *  - per-directory flags honoured for the toolbar controls;
 *  - frontmatter-driven layout (toc/numbering/draft overrides);
 *  - prev/next navigation and breadcrumbs;
 *  - the v2-compatible octet-stream download route.
 */

declare(strict_types=1);

namespace MdViewer\Http\Controllers;

use MdViewer\Core\App;
use MdViewer\Core\Response;
use MdViewer\Files\MarkdownRepository;
use MdViewer\Markdown\RenderCache;
use MdViewer\Markdown\RenderResult;

final class ViewerController
{
    use ViewModelTrait;

    public function __construct(private readonly App $app)
    {
    }

    /** Render the configured home document, or null when there is none. */
    public function showHome(): ?Response
    {
        $home = $this->app->repo()->homeDocument();
        if ($home === null) {
            return null;
        }
        $relative = $this->app->repo()->validator()->relativize($home, $this->app->repo()->browseDir()) ?? '';

        return $this->renderDocument($home, $relative, isHome: true);
    }

    public function show(string $fileParam): Response
    {
        $absolute = $this->app->repo()->resolve($fileParam);
        if ($absolute === null) {
            $this->app->logger->info('viewer: refused or missing file', ['file' => substr($fileParam, 0, 255)]);

            return (new PageController($this->app))->notFound(
                'Document not found',
                'The requested document does not exist, or the path was refused by the security validator.',
            );
        }
        $relative = $this->app->repo()->validator()->relativize($absolute, $this->app->repo()->browseDir()) ?? $fileParam;

        return $this->renderDocument($absolute, $relative);
    }

    /** v2-compatible source download: same validator, opaque attachment. */
    public function download(string $fileParam): Response
    {
        $absolute = $this->app->repo()->resolve($fileParam);
        if ($absolute === null) {
            return Response::text('File not found.', 404)->noStore();
        }

        return Response::download($absolute);
    }

    private function renderDocument(string $absolute, string $relative, bool $isHome = false): Response
    {
        $repo = $this->app->repo();
        $config = $this->app->config;

        $source = MarkdownRepository::read($absolute);
        if ($source === null) {
            return (new PageController($this->app))->notFound('Document unreadable', 'The file exists but could not be read.');
        }

        $stat = @stat($absolute);
        $mtime = is_array($stat) ? (int) $stat['mtime'] : 0;
        $size = is_array($stat) ? (int) $stat['size'] : 0;
        $docDir = dirname($relative) === '.' ? '' : dirname($relative);

        // ── HTTP caching ────────────────────────────────────────────────────
        $fingerprint = $this->app->markdown()->fingerprint();
        $etag = '"' . substr(hash('sha256', $relative . '|' . $mtime . '|' . $size . '|' . $fingerprint), 0, 32) . '"';
        $ifNoneMatch = $this->app->request->header('If-None-Match');
        if ($ifNoneMatch !== null && trim($ifNoneMatch) === $etag) {
            return new Response('', 304, [
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=' . max(0, $config->int('CACHE_TTL')),
            ]);
        }

        // ── Render (with cache) ─────────────────────────────────────────────
        $cache = new RenderCache($config->bool('CACHE_ENABLED'), $this->app->logger);
        $cacheKey = $cache->key($relative, $mtime, $size, $fingerprint);
        $result = $cache->get($cacheKey);
        if ($result === null) {
            $result = $this->app->markdown()->render($source, [
                'docDir' => $docDir,
                'docPath' => $relative,
                'urlBuilder' => fn (array $params): string => $this->app->request->url($params),
                'mediaUrlBuilder' => fn (string $rel): string => $this->app->request->url(['media' => $rel]),
                'fileExists' => fn (string $rel): bool => $repo->resolve($rel) !== null,
            ]);
            $this->applyFrontMatterOverrides($result);
            $cache->put($cacheKey, $result);
        }

        // Draft protection: hidden unless explicitly requested by path.
        if (!$isHome && ($result->frontMatter['draft'] ?? false) === true) {
            return (new PageController($this->app))->notFound('Document not found', '');
        }

        // ── Navigation context ──────────────────────────────────────────────
        $neighbours = $repo->neighbours($relative);
        $neighbourMeta = [];
        foreach (['prev', 'next'] as $direction) {
            $path = $neighbours[$direction];
            if ($path !== null) {
                $abs = $repo->resolve($path);
                $neighbourMeta[$direction] = $abs !== null
                    ? ['path' => $path, 'title' => $repo->quickTitle($abs)]
                    : null;
            } else {
                $neighbourMeta[$direction] = null;
            }
        }

        $flags = $config->flagsFor($repo->validator()->dirSegments($docDir));
        $title = $result->title ?? pathinfo($relative, PATHINFO_FILENAME);
        $siteName = $config->str('SITE_NAME');

        $html = $this->app->tpl->render('viewer/document', [
            'app' => $this->app,
            'bodyPage' => 'doc',
            'pageTitle' => $title . ' — ' . $siteName,
            'pageDescription' => $result->description,
            'jsConfig' => $this->jsConfig($relative, $docDir, [
                'docEtag' => $etag,
                'readingMinutes' => $result->readingMinutes,
                'words' => $result->words,
            ]),
            'result' => $result,
            'relative' => $relative,
            'docDir' => $docDir,
            'isHome' => $isHome,
            'breadcrumbs' => $this->breadcrumbs($relative),
            'neighbours' => $neighbourMeta,
            'flags' => $flags,
            'size' => $this->formatSize($size),
            'modified' => $this->formatDate($mtime),
            'modifiedTs' => $mtime,
            'etag' => $etag,
            'tocEnabled' => $this->app->flags->enabled('AUTO_TOC')
                && (($result->frontMatter['toc'] ?? true) !== false),
            'numberingEnabled' => $this->app->flags->enabled('AUTO_NUMBERING')
                && (($result->frontMatter['numbering'] ?? true) !== false),
        ]);

        return Response::html($html)
            ->withHeader('ETag', $etag)
            ->withHeader('Last-Modified', gmdate('D, d M Y H:i:s', $mtime) . ' GMT')
            ->withCache(max(0, $config->int('CACHE_TTL')));
    }

    /** Frontmatter can hide the TOC per document; handled in the template. */
    private function applyFrontMatterOverrides(RenderResult $result): void
    {
        // Reserved for future per-document overrides that must mutate the
        // render itself; template-level ones live in renderDocument().
    }
}
