<?php

/**
 * MD.Viewer 3.0 — front controller for the public entry point.
 *
 * Routing is query-parameter based so the viewer runs on ANY PHP host
 * without rewrite rules (an .htaccess with pretty URLs ships for Apache).
 *
 *   ?file=path/to/doc.md        render a document (v2-compatible)
 *   ?file=…&download=1          download the source .md (v2-compatible)
 *   ?media=path/to/img.png      serve a content asset
 *   ?dir=path                   file browser at a directory
 *   ?page=browser|themes|settings|search|sitemap|feed
 *   ?api=search|suggest         JSON endpoints (GET)
 *   ?api=render|save|upload     JSON endpoints (POST, CSRF-protected)
 *   ?api=render                 machine render API (POST, `X-Api-Key`/`api_key`)
 *   (no params)                 home document, else file browser
 */

declare(strict_types=1);

namespace MdViewer\Http;

use MdViewer\Core\App;
use MdViewer\Core\Request;
use MdViewer\Core\Response;
use MdViewer\Http\Controllers\ApiController;
use MdViewer\Http\Controllers\BrowserController;
use MdViewer\Http\Controllers\FeedController;
use MdViewer\Http\Controllers\MediaController;
use MdViewer\Http\Controllers\PageController;
use MdViewer\Http\Controllers\RenderApiController;
use MdViewer\Http\Controllers\ViewerController;

final class FrontController
{
    public function __construct(private readonly App $app)
    {
    }

    public function handle(): Response
    {
        $req = $this->app->request;

        // ── POST / JSON mutations (CSRF-protected) ─────────────────────────
        if ($req->isPost()) {
            $api = $req->query('api') ?? $req->post('api') ?? '';
            if ($api === '') {
                return Response::json(['ok' => false, 'error' => 'missing action'], 400);
            }

            // Machine render API: key-authenticated, stateless, no CSRF. Only
            // taken when a key is actually supplied — without one the request
            // keeps the legacy session+CSRF contract below.
            if ($api === 'render' && self::hasApiKey($req)) {
                return (new RenderApiController($this->app->config))->handle($req);
            }

            if (!$this->app->csrf->validate($req)) {
                return Response::json(['ok' => false, 'error' => 'csrf'], 403);
            }
            $controller = new ApiController($this->app);

            return match ($api) {
                'render' => $controller->renderClipboard(),
                'save' => $controller->saveClipboard(),
                'upload' => $controller->upload(),
                default => Response::json(['ok' => false, 'error' => 'unknown action: ' . $api], 400),
            };
        }

        // ── GET ────────────────────────────────────────────────────────────

        // Source download (v2-compatible: ?file=…&download=1)
        if ($req->query('download') === '1' && $req->query('file') !== null) {
            return (new ViewerController($this->app))->download((string) $req->query('file'));
        }

        // Media assets from the content root
        if ($req->query('media') !== null) {
            return (new MediaController($this->app))->serve((string) $req->query('media'));
        }

        // JSON GET endpoints
        $api = $req->query('api');
        if ($api !== null && $api !== '') {
            $controller = new ApiController($this->app);

            return match ($api) {
                'search' => $controller->search(),
                'suggest' => $controller->suggest(),
                'toc' => $controller->toc(),
                'config' => $controller->publicConfig(),
                default => Response::json(['ok' => false, 'error' => 'unknown action'], 400),
            };
        }

        // Named pages
        $page = $req->query('page');
        if ($page !== null && $page !== '') {
            $pages = new PageController($this->app);

            return match ($page) {
                'themes' => $pages->themes(),
                'settings' => $pages->settings(),
                'search' => $pages->search(),
                'browser' => (new BrowserController($this->app))->show($req->query('dir') ?? ''),
                'sitemap' => (new FeedController($this->app))->sitemap(),
                'feed' => (new FeedController($this->app))->feed(),
                default => $pages->notFound(),
            };
        }

        // Document viewer (v2-compatible: ?file=…)
        $file = $req->query('file');
        if ($file !== null && $file !== '') {
            return (new ViewerController($this->app))->show($file);
        }

        // Home document, else the file browser
        $viewer = new ViewerController($this->app);
        if (($response = $viewer->showHome()) !== null) {
            return $response;
        }

        return (new BrowserController($this->app))->show($req->query('dir') ?? '');
    }

    /** True when the request carries a machine-API credential at all. */
    private static function hasApiKey(Request $req): bool
    {
        $header = $req->header('X-Api-Key');
        if ($header !== null && $header !== '') {
            return true;
        }

        return $req->hasQuery('api_key');
    }
}
