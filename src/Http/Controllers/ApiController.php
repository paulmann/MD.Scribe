<?php

/**
 * MD.Viewer 3.0 — JSON API for the browser UI.
 *
 * GET  ?api=search&q=…     full-text search (index-backed)
 * GET  ?api=suggest&q=…    typeahead for the command palette
 * GET  ?api=toc&file=…     heading outline of one document
 * GET  ?api=config         public runtime configuration
 * POST ?api=render         render pasted markdown (clipboard preview)
 * POST ?api=save           save pasted markdown as a file (flag-gated)
 * POST ?api=upload         upload a .md file (flag-gated)
 *
 * POST actions are CSRF-protected (checked in FrontController) and re-check
 * the effective per-directory flags server-side.
 */

declare(strict_types=1);

namespace MdViewer\Http\Controllers;

use MdViewer\Core\App;
use MdViewer\Core\Response;
use MdViewer\Files\MarkdownRepository;
use MdViewer\Markdown\ExcerptService;

final class ApiController
{
    use ViewModelTrait;

    public function __construct(private readonly App $app)
    {
    }

    public function search(): Response
    {
        if (!$this->app->config->bool('FEATURE_SEARCH')) {
            return Response::json(['ok' => false, 'error' => 'search disabled'], 403);
        }
        $q = trim((string) ($this->app->request->query('q') ?? ''));
        $limit = min(100, max(1, (int) ($this->app->request->query('limit') ?? $this->app->config->int('SEARCH_MAX_RESULTS'))));
        if ($q === '' || mb_strlen($q) > 200) {
            return Response::json(['ok' => true, 'query' => $q, 'total' => 0, 'results' => []]);
        }
        $result = $this->app->search()->search($q, $limit);

        return Response::json(['ok' => true] + $result);
    }

    public function suggest(): Response
    {
        $q = trim((string) ($this->app->request->query('q') ?? ''));
        if ($q === '' || mb_strlen($q) > 120) {
            return Response::json(['ok' => true, 'results' => []]);
        }

        return Response::json(['ok' => true, 'results' => $this->app->search()->suggest($q, 12)]);
    }

    public function toc(): Response
    {
        $file = (string) ($this->app->request->query('file') ?? '');
        $absolute = $file !== '' ? $this->app->repo()->resolve($file) : $this->app->repo()->homeDocument();
        if ($absolute === null) {
            return Response::json(['ok' => false, 'error' => 'not found'], 404);
        }
        $source = MarkdownRepository::read($absolute);
        if ($source === null) {
            return Response::json(['ok' => false, 'error' => 'unreadable'], 500);
        }
        [, $body] = ExcerptService::extractFrontMatter($source);

        return Response::json(['ok' => true, 'headings' => ExcerptService::headings($body)]);
    }

    public function publicConfig(): Response
    {
        return Response::json(['ok' => true, 'config' => $this->jsConfig()]);
    }

    /** POST — render pasted markdown for the clipboard preview. */
    public function renderClipboard(): Response
    {
        $dirRel = (string) ($this->app->request->post('dir') ?? '');
        $segments = $this->app->repo()->validator()->dirSegments($dirRel);
        if ($segments === null) {
            return Response::json(['ok' => false, 'error' => 'invalid dir'], 400);
        }
        $flags = $this->app->config->flagsFor($segments);
        if ($flags['DISABLE_CLIPBOARD']) {
            return Response::json(['ok' => false, 'error' => 'clipboard preview disabled for this directory'], 403);
        }

        $markdown = (string) ($this->app->request->post('markdown') ?? '');
        if ($markdown === '' || strlen($markdown) > 4 * 1024 * 1024) {
            return Response::json(['ok' => false, 'error' => 'empty or oversized payload'], 400);
        }

        $result = $this->app->markdown()->render($markdown, [
            'docDir' => implode('/', $segments),
            'docPath' => '',
            'urlBuilder' => fn (array $params): string => $this->app->request->url($params),
            'mediaUrlBuilder' => fn (string $rel): string => $this->app->request->url(['media' => $rel]),
            'fileExists' => fn (string $rel): bool => $this->app->repo()->resolve($rel) !== null,
        ]);

        return Response::json([
            'ok' => true,
            'html' => $result->html,
            'title' => $result->title,
            'headings' => $result->headings,
            'tocHtml' => $result->tocHtml,
            'hasMermaid' => $result->hasMermaid,
            'hasMath' => $result->hasMath,
            'readingMinutes' => $result->readingMinutes,
        ]);
    }

    /** POST — save pasted markdown as a new file (flag-gated). */
    public function saveClipboard(): Response
    {
        $dirRel = (string) ($this->app->request->post('dir') ?? '');
        $content = (string) ($this->app->request->post('content') ?? '');
        $filename = (string) ($this->app->request->post('filename') ?? '');

        if ($content === '' || strlen($content) > 8 * 1024 * 1024) {
            return Response::json(['ok' => false, 'error' => 'empty or oversized payload'], 400);
        }

        $result = $this->app->uploader()->saveMarkdown($content, $filename, $dirRel, 'reader', fromClipboard: true);

        return Response::json($result, (int) ($result['status'] ?? 200));
    }

    /** POST — classic multipart upload of a .md file (flag-gated). */
    public function upload(): Response
    {
        $dirRel = (string) ($this->app->request->post('dir') ?? '');
        $files = $this->app->request->files();
        $file = $files['file'] ?? null;
        if (!is_array($file)) {
            return Response::json(['ok' => false, 'error' => 'missing file'], 400);
        }

        $result = $this->app->uploader()->uploadMarkdown($file, $dirRel, 'reader');

        return Response::json($result, (int) ($result['status'] ?? 200));
    }
}
