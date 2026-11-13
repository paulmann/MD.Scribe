<?php

declare(strict_types=1);

namespace MdViewer\Tests\Feature;

use MdViewer\Core\Logger;
use MdViewer\Files\MarkdownRepository;
use MdViewer\Files\SearchIndex;
use MdViewer\Tests\TestCase;

final class SearchOperatorsTest extends TestCase
{
    private function index(): SearchIndex
    {
        $config = $this->config();
        $logger = new Logger($config, $this->tmpRoot . '/storage');

        return new SearchIndex(
            new MarkdownRepository($config, $logger),
            $logger,
            $this->tmpRoot . '/storage/index',
        );
    }

    private function corpus(): void
    {
        $this->writeContent('docs/runbook/deploy.md', "---\ntags: [ops, deploy]\n---\n# Deploy the service\n\nRun the release script.\n");
        $this->writeContent('notes/meeting.md', "---\ntags: [meeting]\n---\n# Weekly meeting\n\nWe discussed the deploy plan.\n");
        $this->writeContent('docs/guide.md', "# Deploy basics\n\nDeploy from the branch.\n");
        $this->writeContent('releases/notes.md', "---\ntags: [release-notes]\n---\n# Release notes\n\nWhat changed.\n");
    }

    /** @return list<string> */
    private function paths(array $result): array
    {
        $paths = array_column($result['results'], 'path');
        sort($paths);

        return $paths;
    }

    public function testFreeTextStillWorks(): void
    {
        $this->corpus();

        self::assertCount(3, $this->index()->search('deploy')['results']);
    }

    public function testTagOperatorFilters(): void
    {
        $this->corpus();
        $index = $this->index();

        self::assertSame(['docs/runbook/deploy.md'], $this->paths($index->search('deploy tag:ops')));
        self::assertSame(['docs/runbook/deploy.md'], $this->paths($index->search('tag:ops')));
        self::assertSame([], $index->search('deploy tag:missing')['results']);
    }

    public function testPathOperatorIsAPrefixMatch(): void
    {
        $this->corpus();
        $index = $this->index();

        self::assertSame(
            ['docs/guide.md', 'docs/runbook/deploy.md'],
            $this->paths($index->search('deploy path:docs/')),
        );
        self::assertSame(['notes/meeting.md'], $this->paths($index->search('deploy path:notes')));
    }

    public function testQuotedTagIsNormalised(): void
    {
        $this->corpus();
        $index = $this->index();

        // "release notes" normalises to release-notes, the tag on that document.
        self::assertSame(['releases/notes.md'], $this->paths($index->search('tag:"release notes"')));
    }

    public function testFiltersCombine(): void
    {
        $this->corpus();
        $parsed = SearchIndex::parseQuery('deploy tag:ops path:docs/');

        self::assertSame(['deploy'], $parsed['terms']);
        self::assertSame(['ops'], $parsed['tags']);
        self::assertSame('docs', $parsed['path']);

        self::assertSame(['docs/runbook/deploy.md'], $this->paths($this->index()->search('deploy tag:ops path:docs/')));
    }

    public function testColonInProseIsNotAnOperator(): void
    {
        $parsed = SearchIndex::parseQuery('see https://example.com/docs for detail');

        self::assertSame([], $parsed['tags']);
        self::assertNull($parsed['path']);
        self::assertContains('example', $parsed['terms']);
    }

    public function testEmptyQueryWithNoFiltersReturnsNothing(): void
    {
        $this->corpus();
        $result = $this->index()->search('   ');

        self::assertSame(0, $result['total']);
        self::assertSame(['tags' => [], 'path' => null], $result['filters']);
    }
}
