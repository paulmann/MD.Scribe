<?php

declare(strict_types=1);

namespace MdViewer\Tests\Feature;

use MdViewer\Core\FeatureFlags;
use MdViewer\Core\Logger;
use MdViewer\Markdown\MarkdownEngine;
use MdViewer\Tests\TestCase;

final class MarkdownEngineTest extends TestCase
{
    private function engine(array $cookieOverrides = []): MarkdownEngine
    {
        $config = $this->config();
        $flags = new FeatureFlags($config, $cookieOverrides);

        return new MarkdownEngine($flags, $config, new Logger($config, $this->tmpRoot . '/storage'));
    }

    public function testCommonMarkBasics(): void
    {
        $html = $this->engine()->render("# Title\n\nSome **bold** and *italic* and `code`.\n")->html;
        self::assertStringContainsString('<strong>bold</strong>', $html);
        self::assertStringContainsString('<em>italic</em>', $html);
        self::assertStringContainsString('<code class="md-code-inline">code</code>', $html);
    }

    public function testGfmTablesStrikethroughTasklists(): void
    {
        $md = "| a | b |\n|:--|--:|\n| 1 | 2 |\n\n~~gone~~\n\n- [x] done\n- [ ] todo\n";
        $html = $this->engine()->render($md)->html;
        self::assertStringContainsString('<table', $html);
        self::assertStringContainsString('md-table-wrap', $html);
        self::assertStringContainsString('<del>gone</del>', $html);
        self::assertStringContainsString('type="checkbox"', $html);
        self::assertStringContainsString('checked', $html);
    }

    public function testFootnotes(): void
    {
        $html = $this->engine()->render("Text[^1].\n\n[^1]: Note body.\n")->html;
        self::assertStringContainsString('md-footnotes', $html);
        self::assertStringContainsString('href="#fn:1"', $html);
        self::assertStringContainsString('id="fnref:1"', $html);
    }

    public function testDefinitionLists(): void
    {
        $html = $this->engine()->render("Term\n: Definition here\n")->html;
        self::assertStringContainsString('<dt>', $html);
        self::assertStringContainsString('<dd>', $html);
    }

    public function testMathInlineAndBlock(): void
    {
        $result = $this->engine()->render("Cost \$5 and \$10 stay prose. Math \$x^2\$ here.\n\n\$\$\n\\int x\,dx\n\$\$\n");
        self::assertTrue($result->hasMath);
        self::assertStringContainsString('mdx-math-inline', $result->html);
        self::assertStringNotContainsString('mdx-math-inline" data-mdx-math="inline">5', $result->html);
        self::assertStringContainsString('mdx-math-block', $result->html);
        self::assertStringContainsString('\int x\,dx', $result->html);
    }

    public function testMermaidBlock(): void
    {
        $result = $this->engine()->render("```mermaid\ngraph TD\n    A(Start) --> B(End (x))\n```\n");
        self::assertTrue($result->hasMermaid);
        self::assertStringContainsString('<pre class="mermaid"', $result->html);
        self::assertStringContainsString('data-mermaid-source', $result->html);
        // The repair engine quotes the parentheses label
        self::assertStringContainsString('"End (x)"', $result->html);
        self::assertStringContainsString('data-mermaid-fixes="1"', $result->html);
    }

    public function testGithubAlerts(): void
    {
        $html = $this->engine()->render("> [!WARNING]\n> Careful here.\n")->html;
        self::assertStringContainsString('md-alert-warning', $html);
        self::assertStringContainsString('Careful here.', $html);
        self::assertStringNotContainsString('[!WARNING]', $html);
        self::assertStringNotContainsString('<p><br', $html); // no stray softbreak
    }

    public function testPlainBlockquoteWithCopyControl(): void
    {
        $html = $this->engine()->render("> just a quote\n")->html;
        self::assertStringContainsString('<blockquote class="md-quote">', $html);
        self::assertStringContainsString('data-copy-type="quote"', $html);
    }

    public function testEmojiShortcodes(): void
    {
        $html = $this->engine()->render("Party :tada: time 12:30:45 stays.\n")->html;
        self::assertStringContainsString('🎉', $html);
        self::assertStringContainsString('12:30:45', $html);
    }

    public function testSubSupAndMark(): void
    {
        $html = $this->engine()->render("H~2~O and x^2^ and ==marked==.\n")->html;
        self::assertStringContainsString('<sub>2</sub>', $html);
        self::assertStringContainsString('<sup>2</sup>', $html);
        self::assertStringContainsString('<mark>marked</mark>', $html);
    }

    public function testStrikethroughNotBrokenBySubscript(): void
    {
        $html = $this->engine()->render("~~strike~~ and ~sub~.\n")->html;
        self::assertStringContainsString('<del>strike</del>', $html);
        self::assertStringContainsString('<sub>sub</sub>', $html);
    }

    public function testFrontMatterAndHeroSplit(): void
    {
        $result = $this->engine()->render("---\ntitle: \"Kitchen Sink: The Test\"\ntags: [a, b]\n---\n\n# Ignored H1\n");
        self::assertSame('The Test', $result->title);
        self::assertSame('Kitchen Sink', $result->eyebrow);
        self::assertSame(['a', 'b'], $result->frontMatter['tags']);
    }

    public function testHeadingsNumberingAndAnchors(): void
    {
        $result = $this->engine()->render("# One\n\n## Two A\n\n## Two B\n\n### Three\n");
        self::assertSame('one', $result->headings[0]['anchor']);
        self::assertSame('1', $result->headings[0]['number']);
        self::assertSame('1.1', $result->headings[1]['number']);
        self::assertSame('1.2', $result->headings[2]['number']);
        self::assertSame('1.2.1', $result->headings[3]['number']);
        self::assertStringContainsString('id="two-a"', $result->html);
        self::assertStringContainsString('class="md-anchor" href="#two-a"', $result->html);
    }

    public function testManualNumberingDisablesAuto(): void
    {
        $result = $this->engine()->render("# 1. Already numbered\n\n## 1.1 Sub\n");
        self::assertNull($result->headings[0]['number']);
    }

    public function testTocPlaceholder(): void
    {
        $html = $this->engine()->render("# H1\n\n## Section\n\n[TOC]\n")->html;
        self::assertStringContainsString('md-toc-inline', $html);
        self::assertStringNotContainsString('@@MDVTOC@@', $html);
    }

    public function testRelativeMarkdownLinksRewritten(): void
    {
        $this->writeContent('docs/target.md', '# t');
        $engine = $this->engine();
        $html = $engine->render('[go](./target.md#sec) and [miss](./ghost.md)', [
            'docDir' => 'docs',
            'fileExists' => fn (string $rel): bool => $rel === 'docs/target.md',
        ])->html;
        self::assertStringContainsString('?file=docs%2Ftarget.md#sec', $html);
        self::assertStringNotContainsString('md-link-missing', explode('ghost.md', $html)[0]);
        self::assertStringContainsString('md-link-missing', $html);
    }

    public function testRelativeImagesUseMediaRoute(): void
    {
        $engine = $this->engine();
        $html = $engine->render('![alt](img/pic.png "Caption")', [
            'docDir' => 'docs',
            'mediaUrlBuilder' => fn (string $rel): string => '/index.php?media=' . rawurlencode($rel),
        ])->html;
        self::assertStringContainsString('index.php?media=docs%2Fimg%2Fpic.png', $html);
        self::assertStringContainsString('<figure', $html);
        self::assertStringContainsString('<figcaption>Caption</figcaption>', $html);
        self::assertStringContainsString('loading="lazy"', $html);
    }

    public function testRawHtmlEscapedByDefault(): void
    {
        $html = $this->engine()->render("<script>alert(1)</script>\n\n<b>bold</b>\n")->html;
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('<b>bold</b>', $html);
        self::assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testUnsafeLinkSchemesNeverRender(): void
    {
        $html = $this->engine()->render("[x](javascript:alert(1))\n")->html;
        self::assertStringNotContainsString('javascript:', $html);
        self::assertStringContainsString('x', $html);
    }

    public function testGlossaryTablesAnnotatedAndTooltipsInjected(): void
    {
        $result = $this->engine()->render(
            "| Term | Definition |\n| --- | --- |\n| API (Interface) | Contract |\n\nThe API is used.\n",
        );
        self::assertGreaterThan(0, $result->glossaryTerms);
        self::assertStringContainsString('id="tbl-1"', $result->html);
        self::assertStringContainsString('id="gd-tbl-1-0-1" data-gc="1"', $result->html);
        self::assertStringContainsString('<span class="glossary-term" data-gterm="tbl-1:0:2">API</span>', $result->html);
        // inside the glossary table itself, no tooltip injection
        self::assertSame(1, substr_count($result->html, 'glossary-term'));
    }

    public function testUniversalPatternChain(): void
    {
        $html = $this->engine()->render('`(A) --> [B: uses] --> (C)`')->html;
        self::assertStringContainsString('pattern-chain', $html);
        self::assertStringContainsString('pattern-node', $html);
    }

    public function testUniversalPatternRegressionV216(): void
    {
        // The v2.16 infinite-loop input: a lone '<' not followed by '-'.
        $html = $this->engine()->render('`--basetemp=<path>` and `<T>`')->html;
        self::assertStringNotContainsString('pattern-chain', $html);
        self::assertStringContainsString('--basetemp=', $html);
    }

    public function testCodeHighlightingServerSide(): void
    {
        $html = $this->engine()->render("```php\n<?php echo 'hi';\n```\n")->html;
        self::assertStringContainsString('hljs', $html);
        self::assertStringContainsString('language-php', $html);
        self::assertStringContainsString('data-copy-type="code"', $html);
    }

    public function testUnknownLanguageFallsBackToEscaped(): void
    {
        $html = $this->engine()->render("```notalang\n<b>x</b>\n```\n")->html;
        self::assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $html);
    }

    public function testSoftBreaksBecomeBrByDefault(): void
    {
        $html = $this->engine()->render("line one\nline two\n")->html;
        self::assertStringContainsString('<br', $html);
    }

    public function testSoftBreaksOff(): void
    {
        $engine = $this->engine(['mdv_feat_DOUBLE_LINE_BREAKS' => '0']);
        $html = $engine->render("line one\nline two\n")->html;
        self::assertStringNotContainsString('<br', $html);
    }

    public function testFeatureToggleViaCookieChangesFingerprint(): void
    {
        $a = $this->engine()->fingerprint();
        $b = $this->engine(['mdv_feat_FEATURE_EMOJI' => '0'])->fingerprint();
        self::assertNotSame($a, $b);
    }

    public function testReadingTimeAndWords(): void
    {
        $result = $this->engine()->render(str_repeat('word ', 450));
        self::assertGreaterThan(400, $result->words);
        self::assertGreaterThanOrEqual(2, $result->readingMinutes);
    }

    public function testCjkContentRenders(): void
    {
        $result = $this->engine()->render("# 中文标题\n\n这是一段中文内容，测试渲染。\n");
        self::assertSame('中文标题', $result->title);
        self::assertStringContainsString('这是一段中文内容', $result->html);
        self::assertGreaterThan(0, $result->words);
    }
}
