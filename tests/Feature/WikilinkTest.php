<?php

declare(strict_types=1);

namespace MdViewer\Tests\Feature;

use MdViewer\Core\FeatureFlags;
use MdViewer\Core\Logger;
use MdViewer\Markdown\MarkdownEngine;
use MdViewer\Markdown\RenderResult;
use MdViewer\Tests\TestCase;

final class WikilinkTest extends TestCase
{
    /** @param array<string, string> $cookies */
    private function render(string $markdown, string $docPath = 'index.md', array $cookies = []): RenderResult
    {
        $config = $this->config();
        $engine = new MarkdownEngine(
            new FeatureFlags($config, $cookies),
            $config,
            new Logger($config, $this->tmpRoot . '/storage'),
        );

        return $engine->render($markdown, [
            'docDir' => dirname($docPath) === '.' ? '' : dirname($docPath),
            'docPath' => $docPath,
            'urlBuilder' => static fn (array $params): string => '?' . http_build_query($params),
            'mediaUrlBuilder' => static fn (string $rel): string => '?media=' . rawurlencode($rel),
            'fileExists' => fn (string $rel): bool => is_file($this->tmpRoot . '/content/' . $rel),
        ]);
    }

    public function testExistingDocumentBecomesALink(): void
    {
        $this->writeContent('Page.md', "# Page\n");
        $html = $this->render('See [[Page]].')->html;

        self::assertStringContainsString('href="?file=Page.md"', $html);
        self::assertStringContainsString('class="md-wikilink"', $html);
        self::assertStringNotContainsString('md-wikilink-missing', $html);
        self::assertStringContainsString('>Page</a>', $html);
    }

    public function testMissingDocumentIsMarkedButStillLinks(): void
    {
        $html = $this->render('See [[Nope]].')->html;

        self::assertStringContainsString('md-wikilink-missing', $html);
        self::assertStringContainsString('href="?file=Nope.md"', $html);
        self::assertStringContainsString('Not created yet: Nope.md', $html);
    }

    public function testAliasAndAnchor(): void
    {
        $this->writeContent('Page.md', "# Page\n");
        $alias = $this->render('Read [[Page|the page]].')->html;
        self::assertStringContainsString('>the page</a>', $alias);
        self::assertStringContainsString('title="Page"', $alias);

        $anchored = $this->render('Read [[Page#Install Steps]].')->html;
        self::assertStringContainsString('href="?file=Page.md#install-steps"', $anchored);
        self::assertStringContainsString('Page § Install Steps', $anchored);
    }

    public function testRelativeResolutionPrefersTheDocumentDirectory(): void
    {
        $this->writeContent('docs/Page.md', "# Local\n");
        $this->writeContent('Page.md', "# Root\n");

        $html = $this->render('See [[Page]].', 'docs/index.md')->html;

        self::assertStringContainsString('docs%2FPage.md', $html);
    }

    public function testAnchorOnlyLinkStaysInTheDocument(): void
    {
        $html = $this->render('Jump to [[#Install Steps]].')->html;

        self::assertStringContainsString('href="#install-steps"', $html);
        self::assertStringContainsString('#Install Steps</a>', $html);
    }

    public function testCodeIsNeverRewritten(): void
    {
        $inline = $this->render('Write `[[Page]]` to link.')->html;
        self::assertStringNotContainsString('md-wikilink', $inline);

        $fenced = $this->render("```\n[[Page]]\n```\n")->html;
        self::assertStringNotContainsString('md-wikilink', $fenced);
    }

    public function testPathTraversalIsRefused(): void
    {
        $html = $this->render('Read [[../secret]].')->html;

        self::assertStringContainsString('md-wikilink-refused', $html);
        self::assertStringNotContainsString('<a ', $html);
    }

    public function testEmptyBracketsStayLiteral(): void
    {
        $html = $this->render('Nothing [[]] here.')->html;

        self::assertStringNotContainsString('md-wikilink', $html);
        self::assertStringContainsString('[[]]', $html);
    }

    public function testFeatureToggleDisablesTheExtension(): void
    {
        $this->writeContent('Page.md', "# Page\n");
        $html = $this->render('See [[Page]].', 'index.md', ['mdv_feat_FEATURE_WIKILINKS' => '0'])->html;

        self::assertStringNotContainsString('md-wikilink', $html);
        self::assertStringContainsString('[[Page]]', $html);
    }
}
