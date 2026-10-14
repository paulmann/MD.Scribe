<?php

declare(strict_types=1);

namespace MdViewer\Tests\Feature;

use MdViewer\Admin\BackupService;
use MdViewer\Core\AuditLog;
use MdViewer\Core\Logger;
use MdViewer\Files\FileManager;
use MdViewer\Files\MarkdownRepository;
use MdViewer\Files\SearchIndex;
use MdViewer\Files\Uploader;
use MdViewer\Tests\TestCase;
use MdViewer\Theme\ThemeCatalog;

final class FilesAndServicesTest extends TestCase
{
    private function repo(): MarkdownRepository
    {
        return new MarkdownRepository($this->config(), new Logger(null, $this->tmpRoot . '/storage'));
    }

    // ── Repository ─────────────────────────────────────────────────────────

    public function testScanFindsDocumentsAndSkipsVendorDirs(): void
    {
        $this->writeContent('a.md', '# A');
        $this->writeContent('sub/b.md', '# B');
        mkdir($this->tmpRoot . '/content/node_modules', 0o777, true);
        file_put_contents($this->tmpRoot . '/content/node_modules/x.md', 'skip');
        file_put_contents($this->tmpRoot . '/content/.hidden.md', 'skip');
        file_put_contents($this->tmpRoot . '/content/not-md.txt', 'skip');

        $files = $this->repo()->scan();
        $paths = array_column($files, 'path');
        sort($paths);
        self::assertSame(['a.md', 'sub/b.md'], $paths);
    }

    public function testQuickTitleFromFrontmatterOrH1OrFilename(): void
    {
        $repo = $this->repo();
        $f1 = $this->writeContent('t1.md', "---\ntitle: From FM\n---\n\n# Ignored\n");
        $f2 = $this->writeContent('t2.md', "# From H1\n\ntext\n");
        $f3 = $this->writeContent('t3.md', "no heading here\n");
        self::assertSame('From FM', $repo->quickTitle($f1));
        self::assertSame('From H1', $repo->quickTitle($f2));
        self::assertSame('t3', $repo->quickTitle($f3));
    }

    public function testNeighbours(): void
    {
        $this->writeContent('a.md', '# a');
        $this->writeContent('b.md', '# b');
        $this->writeContent('c.md', '# c');
        $n = $this->repo()->neighbours('b.md');
        self::assertSame('a.md', $n['prev']);
        self::assertSame('c.md', $n['next']);
    }

    // ── SearchIndex ────────────────────────────────────────────────────────

    public function testSearchFindsTitleHeadingAndBody(): void
    {
        $this->writeContent('deploy.md', "# Deployment Guide\n\nUse rsync to synchronize files to the server.\n");
        $this->writeContent('other.md', "# Other\n\nNothing relevant.\n");
        $index = new SearchIndex($this->repo(), new Logger(), $this->tmpRoot . '/storage');

        $result = $index->search('rsync');
        self::assertSame(1, $result['total']);
        self::assertSame('deploy.md', $result['results'][0]['path']);
        self::assertStringContainsString('<mark>rsync</mark>', $result['results'][0]['snippet']);

        $byTitle = $index->search('deployment');
        self::assertSame('deploy.md', $byTitle['results'][0]['path']);

        $none = $index->search('zzzqqq');
        self::assertSame(0, $none['total']);
    }

    public function testSearchCjk(): void
    {
        $this->writeContent('cn.md', "# 指南\n\n全文搜索引擎测试。\n");
        $index = new SearchIndex($this->repo(), new Logger(), $this->tmpRoot . '/storage');
        $result = $index->search('搜索');
        self::assertSame(1, $result['total']);
    }

    public function testSuggestReturnsHeadings(): void
    {
        $this->writeContent('guide.md', "# Main Guide\n\n## Installation Steps\n");
        $index = new SearchIndex($this->repo(), new Logger(), $this->tmpRoot . '/storage');
        $suggestions = $index->suggest('install');
        self::assertNotEmpty($suggestions);
        $kinds = array_column($suggestions, 'kind');
        self::assertContains('heading', $kinds);
    }

    // ── Uploader ───────────────────────────────────────────────────────────

    public function testUploadRefusedWhenDisabled(): void
    {
        $uploader = new Uploader($this->config(), $this->repo());
        $result = $uploader->saveMarkdown('# x', 'doc.md', '', 'reader');
        self::assertFalse($result['ok']);
        self::assertSame(403, $result['status']);
    }

    public function testUploadSucceedsWhenEnabledWithUniqueName(): void
    {
        $config = $this->config(['DISABLE_UPLOAD' => false]);
        $this->writeContent('doc.md', 'existing');
        $uploader = new Uploader($config, $this->repo());
        $result = $uploader->saveMarkdown('# new', 'doc.md', '', 'reader');
        self::assertTrue($result['ok']);
        self::assertSame('doc-1.md', $result['path']); // collision-safe
    }

    public function testFilenameSanitization(): void
    {
        $uploader = new Uploader($this->config(['DISABLE_UPLOAD' => false]), $this->repo());
        self::assertSame('ok.md', $uploader->sanitizeFilename('ok.md'));
        self::assertNull($uploader->sanitizeFilename('note'));   // v2 rule: .md only
        self::assertNull($uploader->sanitizeFilename('bad/name.md'));
        self::assertNull($uploader->sanitizeFilename("nul\0.md"));
        self::assertNull($uploader->sanitizeFilename('hack.php'));
        self::assertNull($uploader->sanitizeFilename('.hidden'));
        self::assertSame('отчёт — v2.md', $uploader->sanitizeFilename('отчёт — v2.md'));
    }

    public function testClipboardSaveGate(): void
    {
        $config = $this->config(['DISABLE_UPLOAD' => false]); // clipboard still disabled by default
        $uploader = new Uploader($config, $this->repo());
        $result = $uploader->saveMarkdown('# x', 'clip.md', '', 'reader', fromClipboard: true);
        self::assertFalse($result['ok']);
        self::assertSame(403, $result['status']);
    }

    // ── FileManager ────────────────────────────────────────────────────────

    public function testWriteReadRenameDeleteRestoreCycle(): void
    {
        $audit = new AuditLog(null, $this->tmpRoot . '/storage');
        $fm = new FileManager($this->config(), $this->repo(), new Logger(), $audit);

        $written = $fm->write('notes/first.md', "# First\n", 'tester');
        self::assertTrue($written['ok']);
        self::assertFileExists($this->tmpRoot . '/content/notes/first.md');

        $read = $fm->read('notes/first.md');
        self::assertTrue($read['ok']);
        self::assertSame("# First\n", $read['content']);

        // Conflict detection
        $conflict = $fm->write('notes/first.md', 'changed', 'tester', 'wrong-hash');
        self::assertFalse($conflict['ok']);
        self::assertSame(409, $conflict['status']);

        $renamed = $fm->rename('notes/first.md', 'notes/second.md', 'tester');
        self::assertTrue($renamed['ok']);

        $deleted = $fm->delete('notes/second.md', 'tester');
        self::assertTrue($deleted['ok']);
        self::assertFileDoesNotExist($this->tmpRoot . '/content/notes/second.md');

        $trash = $fm->trashList();
        self::assertCount(1, $trash);
        $restored = $fm->restoreFromTrash($trash[0]['name'], 'tester');
        self::assertTrue($restored['ok']);
        self::assertFileExists($this->tmpRoot . '/content/notes/second.md');

        // Audit trail was written
        $entries = AuditLog::read(50, $this->tmpRoot . '/storage');
        $actions = array_column($entries, 'action');
        self::assertContains('content.create', $actions);
        self::assertContains('content.delete', $actions);
        self::assertContains('content.restore', $actions);
    }

    public function testFolderCreateAndDelete(): void
    {
        $fm = new FileManager($this->config(), $this->repo());
        self::assertTrue($fm->createFolder('a/b')['ok']);
        self::assertDirectoryExists($this->tmpRoot . '/content/a/b');
        self::assertFalse($fm->createFolder('a/b')['ok']); // duplicate
        self::assertTrue($fm->deleteFolder('a/b')['ok']);
        self::assertTrue($fm->deleteFolder('a')['ok']);
        self::assertFalse($fm->deleteFolder('')['ok']); // root refused
    }

    // ── BackupService ──────────────────────────────────────────────────────

    public function testBackupCreateListRestoreDelete(): void
    {
        // Backups go to the real Paths::backups() (tmp root storage).
        $this->writeContent('doc.md', 'version one');
        $service = new BackupService($this->repo(), new Logger());

        $created = $service->create('tester', 'note A');
        self::assertTrue($created['ok']);

        $this->writeContent('doc.md', 'version two');
        $this->writeContent('extra.md', 'added later');

        $list = $service->list();
        self::assertCount(1, $list);
        self::assertSame('note A', $list[0]['note']);

        $restored = $service->restore((string) $list[0]['id'], 'tester');
        self::assertTrue($restored['ok']);
        self::assertSame('version one', file_get_contents($this->tmpRoot . '/content/doc.md'));
        self::assertFileDoesNotExist($this->tmpRoot . '/content/extra.md');

        // Pre-restore safety backup exists
        self::assertCount(2, $service->list());

        $deleted = $service->delete((string) $list[0]['id'], 'tester');
        self::assertTrue($deleted['ok']);
    }

    public function testBackupRefusesTraversalIds(): void
    {
        $service = new BackupService($this->repo(), new Logger());
        self::assertFalse($service->restore('../../etc')['ok']);
        self::assertFalse($service->delete('content-evil')['ok']);
    }

    // ── ThemeCatalog ───────────────────────────────────────────────────────

    public function testThemeCatalogBuildsCss(): void
    {
        $catalog = new ThemeCatalog(dirname(__DIR__, 2) . '/config/themes.php', $this->tmpRoot . '/storage');
        $css = $catalog->buildCss();
        self::assertStringContainsString('[data-neutral="zinc"]', $css);
        self::assertStringContainsString('[data-accent="blue"]', $css);
        self::assertStringContainsString('[data-radius="md"]', $css);
        self::assertStringContainsString('.dark', $css);
        self::assertCount(12, $catalog->presets());
    }

    public function testCustomThemeRoundtrip(): void
    {
        $dir = $this->tmpRoot . '/storage';
        $catalog = new ThemeCatalog(dirname(__DIR__, 2) . '/config/themes.php', $dir);
        $saved = $catalog->saveCustom('brand', 'Brand', ['primary' => '#ff0000', 'evil' => 'url(javascript:x)'], ['primary' => 'oklch(0.6 0.2 25)']);
        self::assertSame('custom-brand', $saved['id']);
        self::assertSame('#ff0000', $saved['light']['primary']);
        self::assertArrayNotHasKey('evil', $saved['light']); // allowlist filters unknown tokens

        $css = $catalog->customCss();
        self::assertStringContainsString('[data-accent="custom-brand"]', $css);

        self::assertTrue($catalog->deleteCustom('custom-brand'));
        self::assertFalse($catalog->deleteCustom('../../evil'));
    }
}
