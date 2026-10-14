<?php

/**
 * MD.Viewer 3.0 — shared view-model helpers for the public controllers.
 *
 * Builds the data every template needs: window.MDV_CONFIG, the effective
 * per-directory flags, breadcrumbs and the theme bootstrap values.
 */

declare(strict_types=1);

namespace MdViewer\Http\Controllers;

use MdViewer\Core\App;
use MdViewer\Core\Version;

trait ViewModelTrait
{
    protected function app(): App
    {
        return $this->app;
    }

    /**
     * window.MDV_CONFIG — the single configuration surface for the JS layer.
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    protected function jsConfig(string $currentFile = '', string $currentDir = '', array $extra = []): array
    {
        $app = $this->app();
        $req = $app->request;

        return array_merge([
            'version' => Version::VERSION,
            'scriptUrl' => $req->scriptUrl(),
            'assetBase' => $req->assetBase(),
            'siteName' => $app->config->str('SITE_NAME'),
            'currentFile' => $currentFile,
            'currentDir' => $currentDir,
            'features' => $app->flags->snapshot(),
            'flags' => $app->config->flagsFor($currentDir === '' ? [] : $app->repo()->validator()->dirSegments($currentDir)),
            'csrf' => $app->csrf->token(),
            'themeDefault' => $app->config->str('THEME_DEFAULT'),
            'modeDefault' => $app->config->str('MODE_DEFAULT'),
            'searchEnabled' => $app->config->bool('FEATURE_SEARCH'),
            'mermaidEnabled' => $app->flags->enabled('FEATURE_MERMAID'),
            'mathEnabled' => $app->flags->enabled('FEATURE_MATH'),
            'maxUploadMb' => $app->config->int('MAX_UPLOAD_MB'),
        ], $extra);
    }

    /**
     * Breadcrumbs for a relative document path.
     *
     * @return list<array{name: string, path: string, isFile: bool}>
     */
    protected function breadcrumbs(string $relativePath): array
    {
        $out = [['name' => $this->app()->config->str('SITE_NAME'), 'path' => '', 'isFile' => false]];
        $segments = explode('/', trim($relativePath, '/'));
        $acc = '';
        $last = count($segments) - 1;
        foreach ($segments as $i => $segment) {
            if ($segment === '') {
                continue;
            }
            $acc = $acc === '' ? $segment : $acc . '/' . $segment;
            $out[] = [
                'name' => $i === $last ? preg_replace('/\.md$/i', '', $segment) : $segment,
                'path' => $acc,
                'isFile' => $i === $last,
            ];
        }

        return $out;
    }

    protected function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        $size = (float) $bytes;
        while ($size >= 1024 && $i < count($units) - 1) {
            $size /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) (int) $size : number_format($size, $size < 10 ? 1 : 0)) . ' ' . $units[$i];
    }

    protected function formatDate(int $timestamp): string
    {
        return $timestamp > 0 ? date('Y-m-d H:i', $timestamp) : '—';
    }
}
