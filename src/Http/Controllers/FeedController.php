<?php

/**
 * MD.Viewer 3.0 — sitemap.xml and RSS feed for the document corpus.
 */

declare(strict_types=1);

namespace MdViewer\Http\Controllers;

use MdViewer\Core\App;
use MdViewer\Core\Response;

final class FeedController
{
    use ViewModelTrait;

    public function __construct(private readonly App $app)
    {
    }

    public function sitemap(): Response
    {
        if (!$this->app->config->bool('SITEMAP_ENABLED')) {
            return Response::text('Sitemap disabled.', 404);
        }
        $req = $this->app->request;
        $repo = $this->app->repo();
        $files = $repo->scan();

        $urls = '';
        foreach ($files as $file) {
            $loc = htmlspecialchars($req->origin() . $req->url(['file' => $file['path']]), ENT_XML1);
            $lastmod = $file['modified'] > 0 ? date('c', (int) $file['modified']) : date('c');
            $urls .= "  <url><loc>{$loc}</loc><lastmod>{$lastmod}</lastmod></url>\n";
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n"
            . $urls
            . "</urlset>\n";

        return Response::xml($xml)->withCache(3600);
    }

    public function feed(): Response
    {
        if (!$this->app->config->bool('RSS_ENABLED')) {
            return Response::text('Feed disabled.', 404);
        }
        $req = $this->app->request;
        $repo = $this->app->repo();
        $config = $this->app->config;

        $files = $repo::sortFiles($repo->scan(), 'modified', 'desc');
        $files = array_slice($files, 0, 30);

        $siteUrl = htmlspecialchars($req->origin() . $req->scriptUrl(), ENT_XML1);
        $siteName = htmlspecialchars($config->str('SITE_NAME'), ENT_XML1);
        $items = '';
        foreach ($files as $file) {
            $abs = $repo->resolve((string) $file['path']);
            $title = htmlspecialchars((string) $file['title'], ENT_XML1);
            $link = htmlspecialchars($req->origin() . $req->url(['file' => $file['path']]), ENT_XML1);
            $date = date('r', (int) $file['modified']);
            $description = '';
            if ($abs !== null) {
                $source = \MdViewer\Files\MarkdownRepository::read($abs) ?? '';
                [, $body] = \MdViewer\Markdown\ExcerptService::extractFrontMatter($source);
                $description = htmlspecialchars(
                    mb_substr(\MdViewer\Markdown\ExcerptService::description($body), 0, 400),
                    ENT_XML1,
                );
            }
            $items .= "    <item>\n      <title>{$title}</title>\n      <link>{$link}</link>\n"
                . "      <guid isPermaLink=\"true\">{$link}</guid>\n      <pubDate>{$date}</pubDate>\n"
                . "      <description>{$description}</description>\n    </item>\n";
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<rss version="2.0"><channel>' . "\n"
            . "  <title>{$siteName}</title>\n  <link>{$siteUrl}</link>\n"
            . "  <description>Recently updated documents</description>\n"
            . "  <lastBuildDate>" . date('r') . "</lastBuildDate>\n"
            . $items
            . "</channel></rss>\n";

        return Response::xml($xml)->withCache(900);
    }
}
