<?php

declare(strict_types=1);

namespace MdViewer\Tests\Feature;

use MdViewer\Core\Logger;
use MdViewer\Files\MarkdownRepository;
use MdViewer\Tests\TestCase;

/**
 * The browse root may be a symlink: the documented release layout puts
 * `<release>/content -> mdscribe-data/content`, and the updater keeps it that way.
 *
 * The scan walks the *resolved* base directory, so the prefix it strips to make paths
 * relative has to be resolved too. When it was not, every file under a sub-directory lost
 * its first character — measured in production on 2026-11-14: `index.md` was listed as
 * `ndex.md` and every link the browser offered answered 404.
 */
final class BrowseRootSymlinkTest extends TestCase
{
    private function repository(): MarkdownRepository
    {
        return new MarkdownRepository($this->config(), new Logger(null, $this->tmpRoot . '/storage'));
    }

    /** @param array<string, string> $files relative path => contents */
    private function makeContentBehindSymlink(array $files): string
    {
        $real = $this->tmpRoot . '/mdscribe-data/content';
        @mkdir($real, 0o777, true);
        foreach ($files as $rel => $contents) {
            $path = $real . '/' . $rel;
            @mkdir(dirname($path), 0o777, true);
            file_put_contents($path, $contents);
        }
        $link = $this->tmpRoot . '/content';
        if (is_dir($link)) {
            @rmdir($link);
        }
        if (!@symlink($real, $link)) {
            self::markTestSkipped('symlinks are not permitted here (Windows needs a privilege); CI covers this');
        }

        return $link;
    }

    public function testSubDirectoryScanKeepsFullNamesWhenTheRootIsASymlink(): void
    {
        $link = $this->makeContentBehindSymlink([
            'index.md' => "# Root\n",
            'autoyap/index.md' => "# Folder index\n",
            'autoyap/autoyap-roles-security-2026-11-13.md' => "# Roles\n",
            'autoyap/autoyap-operations-2026-11-13.md' => "# Ops\n",
        ]);
        $this->config(['BROWSE_DIR' => $link]);
        $repo = $this->repository();

        $paths = array_column($repo->scan('autoyap'), 'path');
        sort($paths);

        self::assertSame([
            'autoyap/autoyap-operations-2026-11-13.md',
            'autoyap/autoyap-roles-security-2026-11-13.md',
            'autoyap/index.md',
        ], $paths, 'names must not be truncated by the symlinked root');

        // And every path the browser would link to must resolve back to a real file.
        foreach ($paths as $path) {
            self::assertNotNull($repo->resolve($path), 'the browser link must resolve: ' . $path);
        }
    }

    public function testRootScanKeepsFullNamesWhenTheRootIsASymlink(): void
    {
        $link = $this->makeContentBehindSymlink([
            'index.md' => "# Root\n",
            'getting-started.md' => "# Start\n",
            'docs/markdown-reference.md' => "# Reference\n",
        ]);
        $this->config(['BROWSE_DIR' => $link]);

        $paths = array_column($this->repository()->scan(), 'path');
        sort($paths);

        self::assertSame(['docs/markdown-reference.md', 'getting-started.md', 'index.md'], $paths);
    }

    /** The defect the production symptom came from, stated as the property it broke. */
    public function testEveryScannedPathResolves(): void
    {
        $link = $this->makeContentBehindSymlink([
            'autoyap/index.md' => "# Folder index\n",
            'autoyap/autoyap-roles-security-2026-11-13.md' => "# Roles\n",
        ]);
        $this->config(['BROWSE_DIR' => $link]);
        $repo = $this->repository();

        foreach ($repo->scan('autoyap') as $file) {
            self::assertNotNull(
                $repo->resolve((string) $file['path']),
                'scanned path does not resolve: ' . (string) $file['path'],
            );
        }
    }
}
