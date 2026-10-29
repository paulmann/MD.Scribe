<?php

declare(strict_types=1);

namespace MdViewer\Tests\Unit;

use MdViewer\Api\RenderError;
use MdViewer\Api\RenderService;
use MdViewer\Tests\TestCase;

/**
 * Machine render service: the three formats, auto-detection and the input
 * limits of the machine API. The Markdown branch runs the real standalone
 * pipeline, so these assertions also pin the embed-mode markup the API returns.
 */
final class RenderServiceTest extends TestCase
{
    private RenderService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new RenderService();
    }

    public function testMarkdownBecomesAFragmentWithHeadingListTableAndCode(): void
    {
        $markdown = "# Заголовок\n\n- раз\n- два\n\n"
            . "| a | b |\n|---|---|\n| 1 | 2 |\n\n"
            . "```php\n<?php echo 1;\n```\n";

        $fragment = $this->service->process($markdown, 'markdown');

        self::assertSame('markdown', $fragment->format);
        self::assertStringContainsString('<h1', $fragment->html);
        self::assertStringContainsString('<ul>', $fragment->html);
        self::assertMatchesRegularExpression('/<li>(?:<p>)?раз/u', $fragment->html);
        self::assertStringContainsString('<table>', $fragment->html);
        self::assertStringContainsString('<pre><code class="hljs language-php">', $fragment->html);
        // Embed mode: a bare fragment, not a page and not viewer chrome.
        self::assertStringNotContainsString('<html', $fragment->html);
        self::assertStringNotContainsString('<body', $fragment->html);
        self::assertStringNotContainsString('md-code-bar', $fragment->html);
        self::assertStringNotContainsString('markdown-body', $fragment->html);
    }

    public function testHtmlIsSanitizedScriptsAndHandlersRemoved(): void
    {
        $fragment = $this->service->process(
            '<p onclick="steal()">ok</p><script>alert(1)</script><a href="javascript:alert(2)">x</a>',
            'html',
        );

        self::assertSame('html', $fragment->format);
        self::assertStringContainsString('<p>ok</p>', $fragment->html);
        self::assertStringNotContainsString('<script', $fragment->html);
        self::assertStringNotContainsString('alert(1)', $fragment->html);
        self::assertStringNotContainsString('onclick', $fragment->html);
        self::assertStringNotContainsString('javascript:', $fragment->html);
    }

    public function testTextIsEscapedInsideWrappingBlock(): void
    {
        $fragment = $this->service->process('просто текст <b>не тег</b>', 'text');

        self::assertSame('text', $fragment->format);
        self::assertStringStartsWith('<div class="md-text"', $fragment->html);
        self::assertStringEndsWith('</div>', $fragment->html);
        self::assertStringContainsString('white-space:pre-wrap', $fragment->html);
        self::assertStringContainsString('&lt;b&gt;не тег&lt;/b&gt;', $fragment->html);
        self::assertStringNotContainsString('<b>', $fragment->html);
    }

    public function testAutoDetectsHtml(): void
    {
        $fragment = $this->service->process('<p>ok</p>');

        self::assertSame('html', $fragment->format);
        self::assertStringContainsString('<p>ok</p>', $fragment->html);
    }

    public function testAutoDetectsMarkdown(): void
    {
        self::assertSame('markdown', $this->service->process("## Заголовок\n\nтекст")->format);
        self::assertSame('markdown', $this->service->process("- раз\n- два")->format);
        self::assertSame('markdown', $this->service->process("**жирный**")->format);
        self::assertSame('markdown', $this->service->process('[текст](https://example.com)')->format);

        $fragment = $this->service->process("# Заголовок\n\n- раз\n");
        self::assertStringContainsString('<h1', $fragment->html);
        self::assertMatchesRegularExpression('/<li>(?:<p>)?раз/u', $fragment->html);
    }

    public function testAutoDetectsPlainText(): void
    {
        $fragment = $this->service->process('просто текст без разметки');

        self::assertSame('text', $fragment->format);
        self::assertStringStartsWith('<div class="md-text"', $fragment->html);
    }

    public function testAutoPrefersHtmlWhenTheFirstTagIsABlockTag(): void
    {
        // Both HTML and Markdown signals are present: HTML wins on the block tag.
        $fragment = $this->service->process("<div>\n\n# не заголовок\n</div>");

        self::assertSame('html', $fragment->format);
    }

    public function testAutoTreatsUnknownAngleBracketContentAsMarkdownOrText(): void
    {
        // `<?php` is not a tag from the block allowlist — no HTML, no MD signal.
        self::assertSame('text', RenderService::detectFormat('<?php echo 1;'));
        // A stray `<` inside prose must not turn the payload into HTML.
        self::assertSame('text', RenderService::detectFormat('a < b и c > d'));
        // One explicit Markdown construct is enough (ties go to Markdown).
        self::assertSame('markdown', RenderService::detectFormat('смотри <тут> и **важно**'));
    }

    public function testEmptyContentIsRejected(): void
    {
        foreach (['', "   \n\t "] as $empty) {
            try {
                $this->service->process($empty);
                self::fail('empty content must be rejected');
            } catch (RenderError $error) {
                self::assertSame('empty_content', $error->errorCode());
                self::assertSame(422, $error->statusCode());
            }
        }
    }

    public function testUnknownFormatIsRejected(): void
    {
        foreach (['html5', 'md', ''] as $format) {
            try {
                $this->service->process('<p>ok</p>', $format);
                self::fail('unknown format must be rejected: ' . $format);
            } catch (RenderError $error) {
                self::assertSame('bad_format', $error->errorCode());
                self::assertSame(422, $error->statusCode());
            }
        }
    }

    public function testContentOverTheLimitIsRejected(): void
    {
        $content = str_repeat('a', RenderService::MAX_CONTENT_BYTES + 1);
        try {
            $this->service->process($content);
            self::fail('oversized content must be rejected');
        } catch (RenderError $error) {
            self::assertSame('too_large', $error->errorCode());
            self::assertSame(413, $error->statusCode());
        }
    }

    public function testContentAtTheLimitIsAccepted(): void
    {
        $fragment = $this->service->process(str_repeat('a', RenderService::MAX_CONTENT_BYTES), 'text');
        self::assertSame('text', $fragment->format);
    }

    public function testFormatsAreCaseInsensitiveAndTrimmed(): void
    {
        self::assertSame('text', $this->service->process('hello', ' TEXT ')->format);
    }
}
