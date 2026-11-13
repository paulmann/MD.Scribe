<?php

declare(strict_types=1);

namespace MdViewer\Tests\Feature;

use MdViewer\Admin\StaticExportService;
use MdViewer\Core\FeatureFlags;
use MdViewer\Core\Logger;
use MdViewer\Files\MarkdownRepository;
use MdViewer\Markdown\MarkdownEngine;
use MdViewer\Tests\TestCase;
use ZipArchive;

final class StaticExportTest extends TestCase
{
    public function testExportProducesASelfContainedSite(): void
    {
        if (!class_exists(ZipArchive::class)) {
            self::markTestSkipped('The zip extension is not available.');
        }

        $config = $this->config(['SITE_NAME' => 'Test Docs']);
        $logger = new Logger($config, $this->tmpRoot . '/storage');
        $repository = new MarkdownRepository($config, $logger);
        $engine = new MarkdownEngine(new FeatureFlags($config), $config, $logger);

        $this->writeContent('index.md', "# Home\n\nRead [the guide](guide.md).\n");
        $this->writeContent('docs/guide.md', "# Guide\n\nBack [home](../index.md).\n");
        mkdir($this->tmpRoot . '/content/img', 0o777, true);
        file_put_contents($this->tmpRoot . '/content/img/pic.png', 'PNG-bytes');

        $service = new StaticExportService(
            $repository,
            $engine,
            $config,
            $logger,
            $this->tmpRoot . '/exports',
        );

        $result = $service->export('test-site');

        self::assertTrue($result['ok'], (string) ($result['error'] ?? ''));
        self::assertSame(2, $result['documents']);
        self::assertSame([], $result['errors']);

        $path = (string) ($result['path'] ?? '');
        self::assertFileExists($path);

        $zip = new ZipArchive();
        self::assertTrue($zip->open($path));
        self::assertNotFalse($zip->locateName('index.html'));
        self::assertNotFalse($zip->locateName('all-documents.html'));
        self::assertNotFalse($zip->locateName('docs/guide.html'));
        self::assertNotFalse($zip->locateName('assets/md-scribe-export.css'));
        self::assertNotFalse($zip->locateName('img/pic.png'));

        $home = (string) $zip->getFromName('index.html');
        self::assertStringContainsString('href="guide.html"', $home);

        $guide = (string) $zip->getFromName('docs/guide.html');
        self::assertStringContainsString('href="../index.html"', $guide);
        $zip->close();

        self::assertNotNull($service->archivePath((string) ($result['file'] ?? '')));
        self::assertNull($service->archivePath('../../etc/passwd'));
        self::assertNull($service->archivePath('nope.zip'));
    }

    public function testEmptyLibraryIsAnError(): void
    {
        $config = $this->config();
        $logger = new Logger($config, $this->tmpRoot . '/storage');
        $service = new StaticExportService(
            new MarkdownRepository($config, $logger),
            new MarkdownEngine(new FeatureFlags($config), $config, $logger),
            $config,
            $logger,
            $this->tmpRoot . '/exports',
        );

        $result = $service->export();

        self::assertFalse($result['ok']);
        self::assertSame('No documents to export.', $result['error']);
    }
}
