<?php

declare(strict_types=1);

namespace MdViewer\Tests\Unit;

use MdViewer\Markdown\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    private function sanitize(string $html, bool $raw = false, bool $iframes = false): string
    {
        return (new HtmlSanitizer($raw, $iframes))->sanitize($html);
    }

    public function testRemovesScriptTags(): void
    {
        $out = $this->sanitize('<p>ok</p><script>alert(1)</script>');
        self::assertStringNotContainsString('<script', $out);
        self::assertStringNotContainsString('alert', $out);
        self::assertStringContainsString('<p>ok</p>', $out);
    }

    public function testRemovesEventHandlers(): void
    {
        $out = $this->sanitize('<img src="x.png" onerror="alert(1)" onload="steal()">', raw: true);
        self::assertStringNotContainsString('onerror', $out);
        self::assertStringNotContainsString('onload', $out);
        self::assertStringContainsString('src="x.png"', $out);
    }

    public function testBlocksJavascriptUrls(): void
    {
        $out = $this->sanitize('<a href="javascript:alert(1)">x</a>', raw: true);
        self::assertStringNotContainsString('javascript:', $out);
        $out2 = $this->sanitize('<a href="JaVaScRiPt:alert(1)">x</a>', raw: true);
        self::assertStringNotContainsString('alert', strtolower($out2));
        $out3 = $this->sanitize('<a href="&#106;avascript:alert(1)">x</a>', raw: true);
        self::assertStringNotContainsString('javascript', strtolower($out3));
    }

    public function testBlocksDataTextHtmlButAllowsRasterDataImages(): void
    {
        $out = $this->sanitize('<a href="data:text/html,<script>alert(1)</script>">x</a>', raw: true);
        self::assertStringNotContainsString('data:text/html', $out);
        $img = $this->sanitize('<img src="data:image/png;base64,iVBORw0KGgo=">', raw: true);
        self::assertStringContainsString('data:image/png', $img);
    }

    public function testIframesRequireFlag(): void
    {
        $out = $this->sanitize('<iframe src="https://evil.example"></iframe>', raw: true);
        self::assertStringNotContainsString('<iframe', $out);
        $allowed = $this->sanitize('<iframe src="https://ok.example"></iframe>', raw: true, iframes: true);
        self::assertStringContainsString('<iframe', $allowed);
    }

    public function testUnwrapsUnknownTagsKeepingChildren(): void
    {
        $out = $this->sanitize('<marquee><b>bold</b></marquee>', raw: true);
        self::assertStringNotContainsString('<marquee', $out);
        self::assertStringContainsString('<b>bold</b>', $out);
    }

    public function testStyleAttributesDroppedWithoutRawHtml(): void
    {
        $out = $this->sanitize('<p style="position:fixed;inset:0">x</p>', raw: false);
        self::assertStringNotContainsString('style=', $out);
    }

    public function testDangerousStylesDroppedEvenWithRawHtml(): void
    {
        $out = $this->sanitize('<p style="width:expression(alert(1))">x</p>', raw: true);
        self::assertStringNotContainsString('expression', $out);
    }

    public function testTaskListInputsSurviveOtherInputsDie(): void
    {
        $out = $this->sanitize('<input type="checkbox" disabled checked><input type="text" value="x"><input type="submit">');
        self::assertStringContainsString('type="checkbox"', $out);
        self::assertStringNotContainsString('type="text"', $out);
        self::assertStringNotContainsString('type="submit"', $out);
    }

    public function testAppGeneratedMarkupSurvives(): void
    {
        $html = '<div class="md-code" data-lang="php"><div class="md-code-bar">'
            . '<button type="button" class="md-copy-btn" data-copy-type="code" aria-label="Copy">'
            . '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path d="M1 1h2"/></svg>'
            . '</button></div><pre><code class="hljs language-php">x</code></pre></div>'
            . '<span class="glossary-term" data-gterm="tbl-1:0:2">API</span>'
            . '<td id="gd-tbl-1-0-1" data-gc="1">t</td>'
            . '<span class="mdx-math" data-mdx-math="inline">x^2</span>';
        $out = $this->sanitize($html);
        self::assertStringContainsString('data-copy-type="code"', $out);
        self::assertStringContainsString('<svg', $out);
        self::assertStringContainsString('data-gterm="tbl-1:0:2"', $out);
        self::assertStringContainsString('id="gd-tbl-1-0-1"', $out);
        self::assertStringContainsString('data-mdx-math="inline"', $out);
    }

    public function testSvgScriptInsideSvgIsRemoved(): void
    {
        $out = $this->sanitize('<svg viewBox="0 0 10 10"><script>alert(1)</script><circle cx="5" cy="5" r="4"/></svg>', raw: true);
        self::assertStringNotContainsString('<script', $out);
        self::assertStringContainsString('<circle', $out);
    }

    public function testForeignObjectAndUseAreRemoved(): void
    {
        $out = $this->sanitize('<svg><foreignObject><body xmlns="http://www.w3.org/1999/xhtml"><script>alert(1)</script></body></foreignObject><use href="#x"/></svg>', raw: true);
        self::assertStringNotContainsString('foreignObject', strtolower($out));
        self::assertStringNotContainsString('<use', $out);
    }

    public function testFootnoteIdsSurvive(): void
    {
        $out = $this->sanitize('<sup id="fnref:1"><a class="md-footnote-ref" href="#fn:1" role="doc-noteref">1</a></sup>'
            . '<div class="md-footnotes"><ol><li id="fn:1" role="doc-endnote"><p>x <a class="md-footnote-backref" href="#fnref:1">↩</a></p></li></ol></div>');
        self::assertStringContainsString('id="fnref:1"', $out);
        self::assertStringContainsString('id="fn:1"', $out);
        self::assertStringContainsString('role="doc-noteref"', $out);
    }

    public function testUtf8SurvivesRoundTrip(): void
    {
        $out = $this->sanitize('<p>Привет — мир 🚀 «ёлка»</p>');
        self::assertStringContainsString('Привет — мир 🚀 «ёлка»', $out);
    }

    public function testFormElementsAreRemoved(): void
    {
        $out = $this->sanitize('<form action="/steal"><input name="pw"><button>go</button></form>', raw: true);
        self::assertStringNotContainsString('<form', $out);
        self::assertStringNotContainsString('<button', $out);
        self::assertStringNotContainsString('name="pw"', $out);
    }

    public function testButtonsOutsideMdNamespaceAreUnwrapped(): void
    {
        $out = $this->sanitize('<button class="fancy" onclick="x()">Click me</button>', raw: true);
        self::assertStringNotContainsString('<button', $out);
        self::assertStringContainsString('Click me', $out); // content kept, tag dropped
    }
}
