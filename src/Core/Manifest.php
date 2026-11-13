<?php

/**
 * MD.Scribe 3.4 — web app manifest (PWA).
 *
 * A pure builder: no filesystem, no globals, no App. The HTTP layer serves the
 * result with `application/manifest+json` (see Response::manifest), which keeps
 * the media type independent of the web server — `.webmanifest` is missing from
 * most mime.types tables, and the usual server-side fix breaks every other type
 * on the host (documented in docs/installation.md).
 */

declare(strict_types=1);

namespace MdViewer\Core;

final class Manifest
{
    public const string MEDIA_TYPE = 'application/manifest+json';

    /**
     * @param string $scope   web prefix of the installation: '' or '/docs'
     * @param string $iconUrl absolute path (or URL) of an icon
     * @return array<string, mixed>
     */
    public static function build(string $name, string $scope, string $iconUrl): array
    {
        $name = trim($name);
        if ($name === '') {
            $name = 'MD.Scribe';
        }

        $prefix = rtrim($scope, '/');
        $relative = $prefix === '' ? './' : $prefix . '/';

        return [
            'name' => $name,
            'short_name' => mb_substr($name, 0, 12),
            'description' => 'Self-hosted Markdown documentation viewer and knowledge base.',
            'start_url' => $relative,
            'scope' => $relative,
            'display' => 'standalone',
            'background_color' => '#ffffff',
            'theme_color' => '#0b62c4',
            'icons' => [[
                'src' => $iconUrl,
                'sizes' => 'any',
                'type' => 'image/svg+xml',
                'purpose' => 'any',
            ]],
        ];
    }
}
