<?php

declare(strict_types=1);

namespace MdViewer\Tests\Standalone;

use MdViewer\Standalone\Renderer;
use MdViewer\Tests\TestCase;

final class RendererTest extends TestCase
{
    public function testEmbedModeRendersPortableCodeBlocksAndNoNumbering(): void
    {
        $path = $this->writeContent('README.md', "# Title\n\n```php\necho 1;\n```\n");
        $result = (new Renderer())->renderFile($path);

        self::assertStringContainsString('<h1', $result->html);
        self::assertStringContainsString('<pre><code class="hljs language-php">', $result->html);
        self::assertStringNotContainsString('md-code-bar', $result->html);
        self::assertStringNotContainsString('md-h-num', $result->html);
    }

    public function testBaseUrlRewritesRelativeLinksAndMedia(): void
    {
        $path = $this->writeContent('README.md', "[guide](docs/guide.md)\n\n![pic](img/a.png)\n");
        $result = (new Renderer())->renderFileWithBase($path, 'https://repo.example.com/');

        self::assertStringContainsString('href="https://repo.example.com/docs/guide.md"', $result->html);
        self::assertStringContainsString('src="https://repo.example.com/img/a.png"', $result->html);
    }

    public function testViewerModeKeepsToolbarAndNumbering(): void
    {
        $path = $this->writeContent('README.md', "# Title\n\n```js\nalert(1);\n```\n");
        $renderer = new Renderer(null, ['mdv_feat_AUTO_NUMBERING' => '1']);
        $result = $renderer->renderFile($path, ['plainCodeBlocks' => false]);

        self::assertStringContainsString('md-code-bar', $result->html);
        self::assertStringContainsString('md-h-num', $result->html);
    }
}
