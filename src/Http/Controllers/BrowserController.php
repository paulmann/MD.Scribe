<?php

/**
 * MD.Viewer 3.0 — file browser controller.
 *
 * The recursive markdown browser: a searchable, sortable table of every
 * document under the browse root (or a chosen sub-directory), plus folder
 * navigation, upload and clipboard-preview entry points (flag-gated).
 */

declare(strict_types=1);

namespace MdViewer\Http\Controllers;

use MdViewer\Core\App;
use MdViewer\Core\Response;

final class BrowserController
{
    use ViewModelTrait;

    public function __construct(private readonly App $app)
    {
    }

    public function show(string $dirRel = ''): Response
    {
        $repo = $this->app->repo();
        $dirRel = trim($dirRel);
        $segments = $repo->validator()->dirSegments($dirRel);
        if ($segments === null || ($dirRel !== '' && $repo->validator()->validateDir($dirRel, $repo->browseDir()) === null)) {
            return (new PageController($this->app))->notFound('Directory not found', 'The requested directory does not exist or was refused.');
        }
        $dirRel = implode('/', $segments);

        $files = $repo->scan($dirRel === '' ? null : $dirRel);
        $files = $repo::sortFiles(
            $files,
            (string) $this->app->config->get('SORT_DEFAULT_KEY'),
            (string) $this->app->config->get('SORT_DEFAULT_DIR'),
        );

        // Folder overview: direct sub-directories of the current directory
        // with document counts (for the chips row).
        $folders = $this->folders($dirRel, $files);

        $flags = $this->app->config->flagsFor($segments);
        $totalBytes = array_sum(array_column($files, 'size'));

        $html = $this->app->tpl->render('browser/index', [
            'app' => $this->app,
            'bodyPage' => 'browser',
            'pageTitle' => ($dirRel !== '' ? $dirRel . ' — ' : '') . 'Library — ' . $this->app->config->str('SITE_NAME'),
            'pageDescription' => 'Browse and search every Markdown document in the library.',
            'jsConfig' => $this->jsConfig('', $dirRel, ['fileCount' => count($files)]),
            'files' => $files,
            'folders' => $folders,
            'dir' => $dirRel,
            'breadcrumbs' => $this->breadcrumbs($dirRel !== '' ? $dirRel . '/index' : ''),
            'flags' => $flags,
            'totalSize' => $this->formatSize($totalBytes),
            'sortKey' => (string) $this->app->config->get('SORT_DEFAULT_KEY'),
            'sortDir' => (string) $this->app->config->get('SORT_DEFAULT_DIR'),
        ]);

        return Response::html($html)->noStore();
    }

    /**
     * Direct sub-foldors of $dirRel with counts of documents inside.
     *
     * @param list<array{path: string}> $files
     * @return list<array{name: string, path: string, count: int}>
     */
    private function folders(string $dirRel, array $files): array
    {
        $prefix = $dirRel === '' ? '' : $dirRel . '/';
        $counts = [];
        foreach ($files as $file) {
            $path = (string) $file['path'];
            if ($prefix !== '' && !str_starts_with($path, $prefix)) {
                continue;
            }
            $rest = substr($path, strlen($prefix));
            if (!str_contains($rest, '/')) {
                continue;
            }
            $folder = explode('/', $rest)[0];
            $counts[$folder] = ($counts[$folder] ?? 0) + 1;
        }
        ksort($counts, SORT_NATURAL | SORT_FLAG_CASE);
        $out = [];
        foreach ($counts as $name => $count) {
            $out[] = ['name' => (string) $name, 'path' => $prefix . $name, 'count' => $count];
        }

        return $out;
    }
}
