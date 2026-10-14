<?php

/**
 * MD.Viewer 3.0 — media asset server.
 *
 * Serves images and a small allowlist of document/media types from the
 * content root through `?media=path`. This is what makes relative image
 * links work even when the content root is NOT the web root (the v3 layout).
 *
 * Security:
 *  - the same PathValidator layers as documents, plus an extension allowlist;
 *  - SVG is always an attachment (never inline — it is an HTML dialect);
 *  - xml/txt/csv/json are served as text/plain (never as a renderable type);
 *  - X-Content-Type-Options: nosniff on everything;
 *  - ETag/Last-Modified with long max-age for cacheable types.
 */

declare(strict_types=1);

namespace MdViewer\Http\Controllers;

use MdViewer\Core\App;
use MdViewer\Core\Response;

final class MediaController
{
    private const array INLINE_MIME = [
        'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
        'gif' => 'image/gif', 'webp' => 'image/webp', 'avif' => 'image/avif',
        'bmp' => 'image/bmp', 'ico' => 'image/x-icon',
        'pdf' => 'application/pdf',
        'mp4' => 'video/mp4', 'webm' => 'video/webm',
        'mp3' => 'audio/mpeg', 'ogg' => 'audio/ogg', 'wav' => 'audio/wav',
    ];

    private const array TEXT_MIME = [
        'txt' => 'text/plain; charset=utf-8',
        'csv' => 'text/plain; charset=utf-8',
        'json' => 'text/plain; charset=utf-8',
        'xml' => 'text/plain; charset=utf-8',
    ];

    public function __construct(private readonly App $app)
    {
    }

    public function serve(string $relative): Response
    {
        $repo = $this->app->repo();
        $absolute = $repo->validator()->validateMedia($relative, $repo->browseDir());
        if ($absolute === null || !is_readable($absolute)) {
            return Response::text('Not found.', 404)->noStore();
        }

        $ext = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
        $stat = @stat($absolute);
        $mtime = is_array($stat) ? (int) $stat['mtime'] : time();
        $size = is_array($stat) ? (int) $stat['size'] : 0;
        $etag = '"' . substr(hash('sha256', $absolute . '|' . $mtime . '|' . $size), 0, 32) . '"';

        $ifNoneMatch = $this->app->request->header('If-None-Match');
        if ($ifNoneMatch !== null && trim($ifNoneMatch) === $etag) {
            return new Response('', 304, ['ETag' => $etag, 'Cache-Control' => 'public, max-age=604800']);
        }

        $name = basename($absolute);
        $ascii = str_replace(['"', '\\'], '_', (string) preg_replace('/[^\x20-\x7E]/', '_', $name));

        // SVG and archives are always attachments.
        if (in_array($ext, ['svg', 'zip'], true)) {
            return new Response((string) file_get_contents($absolute), 200, [
                'Content-Type' => $ext === 'svg' ? 'image/svg+xml' : 'application/zip',
                'Content-Disposition' => 'attachment; filename="' . ($ascii !== '' ? $ascii : 'download') . '"',
                'X-Content-Type-Options' => 'nosniff',
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=604800',
                'Content-Length' => (string) $size,
            ]);
        }

        if (isset(self::INLINE_MIME[$ext])) {
            return new Response((string) file_get_contents($absolute), 200, [
                'Content-Type' => self::INLINE_MIME[$ext],
                'X-Content-Type-Options' => 'nosniff',
                'ETag' => $etag,
                'Last-Modified' => gmdate('D, d M Y H:i:s', $mtime) . ' GMT',
                'Cache-Control' => 'public, max-age=604800',
                'Content-Length' => (string) $size,
            ]);
        }

        if (isset(self::TEXT_MIME[$ext])) {
            return new Response((string) file_get_contents($absolute), 200, [
                'Content-Type' => self::TEXT_MIME[$ext],
                'X-Content-Type-Options' => 'nosniff',
                'ETag' => $etag,
                'Cache-Control' => 'public, max-age=3600',
                'Content-Length' => (string) $size,
            ]);
        }

        return Response::text('Not found.', 404)->noStore();
    }
}
