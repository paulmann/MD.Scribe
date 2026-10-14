<?php

/**
 * MD.Viewer 3.0 — static-ish pages: Themes catalog, Settings, Search, 404.
 */

declare(strict_types=1);

namespace MdViewer\Http\Controllers;

use MdViewer\Core\App;
use MdViewer\Core\Config;
use MdViewer\Core\Response;

final class PageController
{
    use ViewModelTrait;

    public function __construct(private readonly App $app)
    {
    }

    /** The Themes catalog page (a first-class page, per product spec). */
    public function themes(): Response
    {
        $catalog = $this->app->themes();

        $html = $this->app->tpl->render('pages/themes', [
            'app' => $this->app,
            'bodyPage' => 'themes',
            'pageTitle' => 'Themes — ' . $this->app->config->str('SITE_NAME'),
            'pageDescription' => 'Pick a theme: neutral palette, accent color, corner radius and font — light and dark included.',
            'jsConfig' => $this->jsConfig(),
            'presets' => $catalog->presets(),
            'neutrals' => $catalog->neutralChoices(),
            'accents' => $catalog->accentChoices(),
            'radii' => array_keys((array) ($catalog->data()['radii'] ?? [])),
            'fonts' => (array) ($catalog->data()['fonts'] ?? []),
            'customCss' => $catalog->customCss(),
        ]);

        return Response::html($html)->noStore();
    }

    /** The appearance & features settings page. */
    public function settings(): Response
    {
        $schema = Config::schema();
        $sections = ['features' => [], 'interface' => []];
        foreach ($schema as $key => $meta) {
            if (($meta['feature'] ?? false) !== true) {
                continue;
            }
            $section = $meta['section'] === 'interface' ? 'interface' : 'features';
            $sections[$section][] = [
                'key' => $key,
                'label' => $meta['label'],
                'description' => $meta['description'],
                'type' => $meta['type'],
                'default' => $meta['default'],
                'hardLocked' => $this->app->config->isExplicit($key),
            ];
        }

        $html = $this->app->tpl->render('pages/settings', [
            'app' => $this->app,
            'bodyPage' => 'settings',
            'pageTitle' => 'Settings — ' . $this->app->config->str('SITE_NAME'),
            'pageDescription' => 'Reading preferences and rendering features.',
            'jsConfig' => $this->jsConfig(),
            'sections' => $sections,
            'themeDefault' => $this->app->config->str('THEME_DEFAULT'),
        ]);

        return Response::html($html)->noStore();
    }

    /** Full-text search results page. */
    public function search(): Response
    {
        if (!$this->app->config->bool('FEATURE_SEARCH')) {
            return $this->notFound('Search disabled', 'Search is disabled on this installation.');
        }
        $q = trim((string) ($this->app->request->query('q') ?? ''));
        $results = null;
        if ($q !== '' && mb_strlen($q) <= 200) {
            $results = $this->app->search()->search($q, $this->app->config->int('SEARCH_MAX_RESULTS'));
        }

        $html = $this->app->tpl->render('pages/search', [
            'app' => $this->app,
            'bodyPage' => 'search',
            'pageTitle' => ($q !== '' ? '“' . $q . '” — ' : '') . 'Search — ' . $this->app->config->str('SITE_NAME'),
            'pageDescription' => 'Full-text search across every document.',
            'jsConfig' => $this->jsConfig(),
            'q' => $q,
            'results' => $results,
        ]);

        return Response::html($html)->noStore();
    }

    public function notFound(string $title = 'Not found', string $message = ''): Response
    {
        $html = $this->app->tpl->render('pages/notfound', [
            'app' => $this->app,
            'bodyPage' => 'notfound',
            'pageTitle' => $title . ' — ' . $this->app->config->str('SITE_NAME'),
            'pageDescription' => '',
            'jsConfig' => $this->jsConfig(),
            'title' => $title,
            'message' => $message,
        ]);

        return Response::html($html, 404)->noStore();
    }
}
