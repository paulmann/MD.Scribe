<?php

declare(strict_types=1);

namespace MdViewer\Tests\Unit;

use MdViewer\Files\PathValidator;
use MdViewer\Tests\TestCase;

/**
 * The security heart of MD.Viewer: every layer of the v2.18 validator,
 * re-proven for v3.
 */
final class PathValidatorTest extends TestCase
{
    private PathValidator $validator;
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new PathValidator(8);
        $this->base = $this->tmpRoot . '/content';
        $this->writeContent('doc.md', '# hi');
        $this->writeContent('docs/nested/deep.md', '# deep');
        $this->writeContent('Документ с пробелами.md', '# ru');
        $this->writeContent('Промт v2 — мастер-отчёт.md', '# dash');
        $this->writeContent('img/pic.png', 'binary');
        mkdir($this->tmpRoot . '/secret', 0o777, true);
        file_put_contents($this->tmpRoot . '/secret/leak.md', 'secret');
    }

    public function testAcceptsSimpleValidPath(): void
    {
        self::assertSame(realpath($this->base . '/doc.md'), $this->validator->validateFile('doc.md', $this->base));
    }

    public function testAcceptsNestedPath(): void
    {
        self::assertSame(realpath($this->base . '/docs/nested/deep.md'), $this->validator->validateFile('docs/nested/deep.md', $this->base));
    }

    public function testAcceptsUnicodeNames(): void
    {
        self::assertNotNull($this->validator->validateFile('Документ с пробелами.md', $this->base));
        self::assertNotNull($this->validator->validateFile('Промт v2 — мастер-отчёт.md', $this->base));
    }

    /** @return list<array{string}> */
    public static function rejectedPaths(): array
    {
        return [
            'empty' => [''],
            'too long' => [str_repeat('a', 256) . '.md'],
            'nul byte' => ["do\0c.md"],
            'control chars' => ["do\x08c.md"],
            'url-encoded traversal' => ['%2e%2e%2fsecret%2fleak.md'],
            'double-encoded traversal' => ['..%252f..%252fetc/passwd'],
            'plain traversal' => ['../secret/leak.md'],
            'traversal mid-path' => ['docs/../../secret/leak.md'],
            'trailing traversal' => ['doc.md/..'],
            'absolute unix' => ['/etc/passwd.md'],
            'absolute windows' => ['C:\\Windows\\win.md'],
            'unc path' => ['\\\\server\\share.md'],
            'no extension' => ['doc'],
            'wrong extension' => ['doc.php'],
            'double extension trick' => ['doc.md.php'],
            'directory not file' => ['docs'],
            'missing file' => ['ghost.md'],
            'forbidden char percent' => ['100%.md'],
            'forbidden char question' => ['what?.md'],
            'forbidden char star' => ['all*.md'],
            'forbidden char colon' => ['a:b.md'],
            'forbidden char quote' => ['say".md'],
            'too deep' => ['a/b/c/d/e/f/g/h/i/j/k.md'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('rejectedPaths')]
    public function testRejectsDangerousPaths(string $path): void
    {
        self::assertNull($this->validator->validateFile($path, $this->base), "Path should be rejected: {$path}");
    }

    public function testBackslashIsNormalizedLikeV2(): void
    {
        // v2 Layer 6: single backslashes are normalized to '/' (Windows
        // friendliness), not rejected — the resolved file must be the same.
        self::assertSame(
            realpath($this->base . '/docs/nested/deep.md'),
            $this->validator->validateFile('docs\\nested\\deep.md', $this->base),
        );
    }

    public function testRejectsSymlinkEscape(): void
    {
        $link = $this->base . '/evil-link.md';
        if (!@symlink($this->tmpRoot . '/secret/leak.md', $link)) {
            self::markTestSkipped('symlink() unavailable');
        }
        self::assertNull($this->validator->validateFile('evil-link.md', $this->base));
    }

    public function testDirSegments(): void
    {
        self::assertSame([], $this->validator->dirSegments(''));
        self::assertSame(['docs'], $this->validator->dirSegments('docs'));
        self::assertSame(['docs', 'nested'], $this->validator->dirSegments('docs/nested'));
        self::assertSame(['docs'], $this->validator->dirSegments('./docs'));
        self::assertNull($this->validator->dirSegments('../x'));
        self::assertNull($this->validator->dirSegments('/abs'));
        self::assertNull($this->validator->dirSegments("nul\0"));
        self::assertNull($this->validator->dirSegments('bad%dir'));
        self::assertSame(['папка'], $this->validator->dirSegments('папка'));
    }

    public function testValidateDir(): void
    {
        self::assertSame(realpath($this->base), $this->validator->validateDir('', $this->base));
        self::assertSame(realpath($this->base . '/docs'), $this->validator->validateDir('docs', $this->base));
        self::assertNull($this->validator->validateDir('../secret', $this->base));
        self::assertNull($this->validator->validateDir('ghost', $this->base));
    }

    public function testValidateNewPath(): void
    {
        $target = $this->validator->validateNewPath('docs/new-file.md', $this->base);
        self::assertNotNull($target);
        self::assertStringEndsWith('docs/new-file.md', (string) $target);

        self::assertNull($this->validator->validateNewPath('../evil.md', $this->base));
        self::assertNull($this->validator->validateNewPath('docs/x.php', $this->base));
        self::assertNull($this->validator->validateNewPath('ghost-dir/x.md', $this->base)); // parent must exist
        self::assertNotNull($this->validator->validateNewPath('docs/newdir', $this->base, allowDirs: true));
    }

    public function testMediaValidation(): void
    {
        self::assertNotNull($this->validator->validateMedia('img/pic.png', $this->base));
        self::assertNull($this->validator->validateMedia('doc.md', $this->base));      // .md is not media
        self::assertNull($this->validator->validateMedia('../secret/leak.md', $this->base));
    }

    public function testRelativize(): void
    {
        $abs = $this->base . '/docs/nested/deep.md';
        self::assertSame('docs/nested/deep.md', $this->validator->relativize($abs, $this->base));
        self::assertNull($this->validator->relativize($this->tmpRoot . '/secret/leak.md', $this->base));
    }
}
