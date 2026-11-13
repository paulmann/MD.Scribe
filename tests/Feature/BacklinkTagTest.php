<?php

declare(strict_types=1);

namespace MdViewer\Tests\Feature;

use MdViewer\Core\Logger;
use MdViewer\Files\BacklinkIndex;
use MdViewer\Files\MarkdownRepository;
use MdViewer\Files\TagService;
use MdViewer\Files\WikilinkResolver;
use MdViewer\Tests\TestCase;

final class BacklinkTagTest extends TestCase
{
    private function logger(): Logger
    {
        return new Logger($this->config(), $this->tmpRoot . '/storage');
    }

    private function repository(): MarkdownRepository
    {
        return new MarkdownRepository($this->config(), $this->logger());
    }

    private function backlinks(): BacklinkIndex
    {
        $repo = $this->repository();

        return new BacklinkIndex($repo, new WikilinkResolver($repo), $this->logger(), $this->tmpRoot . '/storage/index');
    }

    private function tags(): TagService
    {
        return new TagService($this->repository(), $this->logger(), $this->tmpRoot . '/storage/index');
    }

    public function testBacklinksAreCollectedAndDeduplicated(): void
    {
        $this->writeContent('a.md', "# A\n\nSee [[b]] and [[B|the b page]] and [[missing]].\n\n```\n[[ignored]]\n```\n");
        $this->writeContent('b.md', "# B\n");

        $entries = $this->backlinks()->forDocument('b.md');

        self::assertCount(1, $entries);
        self::assertSame('a.md', $entries[0]['from']);
        self::assertSame('A', $entries[0]['title']);

        $missing = $this->backlinks()->forDocument('missing.md');
        self::assertCount(1, $missing);

        // A link inside a fenced block is not a link.
        self::assertSame([], $this->backlinks()->forDocument('ignored.md'));
    }

    public function testBacklinkIndexRefreshesWhenTheCorpusChanges(): void
    {
        $this->writeContent('a.md', "[[target]]\n");
        $this->writeContent('target.md', "# Target\n");

        $index = $this->backlinks();
        self::assertCount(1, $index->forDocument('target.md'));

        $this->writeContent('b.md', "[[target]]\n");

        $fresh = $this->backlinks();
        $entries = $fresh->forDocument('target.md');

        self::assertCount(2, $entries);
        self::assertSame(['a.md', 'b.md'], array_column($entries, 'from'));
    }

    public function testSelfLinksAreNotBacklinks(): void
    {
        $this->writeContent('self.md', "[[self]]\n");

        self::assertSame([], $this->backlinks()->forDocument('self.md'));
    }

    public function testCorruptIndexIsRebuilt(): void
    {
        $this->writeContent('a.md', "[[b]]\n");
        $this->writeContent('b.md', "# B\n");

        $dir = $this->tmpRoot . '/storage/index';
        mkdir($dir, 0o777, true);
        file_put_contents($dir . '/backlinks.json', '{not json');

        self::assertCount(1, $this->backlinks()->forDocument('b.md'));
    }

    public function testTagsComeFromFrontmatterInBothForms(): void
    {
        $this->writeContent('b.md', "---\ntags: [docs, Guide]\n---\n# B\n");
        $this->writeContent('c.md', "---\ntags:\n  - docs\n  - API\n---\n# C\n");

        $tags = $this->tags()->all();

        self::assertArrayHasKey('docs', $tags);
        self::assertSame(['b.md', 'c.md'], array_column($tags['docs'], 'path'));
        self::assertSame(['b.md'], array_column($tags['guide'], 'path'));
        self::assertSame(['c.md'], array_column($tags['api'], 'path'));
    }

    public function testTagLookupIsCaseInsensitive(): void
    {
        $this->writeContent('b.md', "---\ntags: [Docs]\n---\n# B\n");

        self::assertSame(['b.md'], array_column($this->tags()->documents('DOCS'), 'path'));
        self::assertSame([], $this->tags()->documents('nope'));
    }

    public function testTagNormalization(): void
    {
        self::assertSame('docs', TagService::normalizeTag('  #Docs '));
        self::assertSame('my-tag', TagService::normalizeTag('My Tag'));
        self::assertSame('api', TagService::normalizeTag('api!!'));
        self::assertNull(TagService::normalizeTag('###'));
        self::assertSame(40, mb_strlen((string) TagService::normalizeTag(str_repeat('a', 80))));
    }

    public function testTagIndexRefreshesWhenAFrontmatterChanges(): void
    {
        $this->writeContent('b.md', "---\ntags: [one]\n---\n# B\n");
        self::assertSame(['b.md'], array_column($this->tags()->documents('one'), 'path'));

        // Note: the corpus stamp is path+mtime+size (same trade-off as the
        // search index), so this edit changes the size on purpose — a same-size
        // edit inside the same second is documented as a known limitation.
        $this->writeContent('b.md', "---\ntags: [second]\n---\n# B\n");

        self::assertSame([], $this->tags()->documents('one'));
        self::assertSame(['b.md'], array_column($this->tags()->documents('second'), 'path'));
    }
}
