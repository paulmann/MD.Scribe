<?php

declare(strict_types=1);

namespace MdViewer\Tests\Feature;

use MdViewer\Core\Logger;
use MdViewer\Files\FileManager;
use MdViewer\Files\MarkdownRepository;
use MdViewer\Files\RevisionService;
use MdViewer\Tests\TestCase;

final class RevisionServiceTest extends TestCase
{
    private function service(?int $max = null): RevisionService
    {
        $config = $this->config($max !== null ? ['REVISIONS_MAX' => $max] : []);

        return new RevisionService($config, new Logger($config, $this->tmpRoot . '/storage'), $this->tmpRoot . '/storage/revisions');
    }

    public function testSaveListRead(): void
    {
        $service = $this->service();

        $saved = $service->save('docs/page.md', "# Version 1\n", 'tester');
        self::assertNotNull($saved);

        $list = $service->listRevisions('docs/page.md');
        self::assertCount(1, $list);
        self::assertSame('tester', $list[0]['actor']);
        self::assertSame("# Version 1\n", $service->read('docs/page.md', $list[0]['id']));
    }

    public function testIdenticalContentIsNotStoredTwice(): void
    {
        $service = $this->service();

        self::assertNotNull($service->save('a.md', "same\n"));
        self::assertNull($service->save('a.md', "same\n"));
        self::assertCount(1, $service->listRevisions('a.md'));
    }

    public function testPruneKeepsTheNewestEntries(): void
    {
        $service = $this->service(3);

        foreach (['v1', 'v2', 'v3', 'v4', 'v5'] as $version) {
            self::assertNotNull($service->save('a.md', $version . "\n"));
        }

        $list = $service->listRevisions('a.md');
        self::assertCount(3, $list);
        self::assertSame("v5\n", $service->read('a.md', $list[0]['id']));
        self::assertSame("v3\n", $service->read('a.md', $list[2]['id']));
    }

    public function testUnknownOrMalformedRevisionIdReturnsNull(): void
    {
        $service = $this->service();
        $service->save('a.md', "content\n");

        self::assertNull($service->read('a.md', '../../etc/passwd'));
        self::assertNull($service->read('a.md', '0000000000-deadbeef'));
        self::assertNull($service->read('a.md', 'not-an-id'));
    }

    public function testDocumentsListsHistoryOwners(): void
    {
        $service = $this->service();
        $service->save('docs/a.md', "a\n", 'alice');
        $service->save('docs/b.md', "b\n", 'bob');

        $documents = $service->documents();

        self::assertCount(2, $documents);
        $paths = array_column($documents, 'path');
        sort($paths);
        self::assertSame(['docs/a.md', 'docs/b.md'], $paths);
    }

    public function testDisabledServiceWritesNothing(): void
    {
        $config = $this->config(['REVISIONS_ENABLED' => false]);
        $service = new RevisionService($config, new Logger($config, $this->tmpRoot . '/storage'), $this->tmpRoot . '/storage/revisions');

        self::assertNull($service->save('a.md', "content\n"));
        self::assertSame([], $service->listRevisions('a.md'));
    }

    public function testFileManagerStoresARevisionOnEverySave(): void
    {
        $config = $this->config(['REVISIONS_MAX' => 5]);
        $logger = new Logger($config, $this->tmpRoot . '/storage');
        $repo = new MarkdownRepository($config, $logger);
        $revisions = new RevisionService($config, $logger, $this->tmpRoot . '/storage/revisions');
        $manager = new FileManager($config, $repo, $logger, null, $revisions);

        $first = $manager->write('docs/page.md', "# One\n", 'admin');
        self::assertTrue($first['ok']);
        $second = $manager->write('docs/page.md', "# Two\n", 'admin');
        self::assertTrue($second['ok']);

        $list = $revisions->listRevisions('docs/page.md');
        self::assertCount(2, $list);
        self::assertSame("# Two\n", $revisions->read('docs/page.md', $list[0]['id']));
        self::assertSame("# One\n", $revisions->read('docs/page.md', $list[1]['id']));
    }
}
