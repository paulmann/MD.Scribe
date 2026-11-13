<?php

declare(strict_types=1);

namespace MdViewer\Tests\Unit;

use MdViewer\Files\WikilinkResolver;
use MdViewer\Tests\TestCase;

final class WikilinkResolverTest extends TestCase
{
    public function testNormalize(): void
    {
        self::assertSame('Page.md', WikilinkResolver::normalize('Page'));
        self::assertSame('Page.md', WikilinkResolver::normalize('./Page'));
        self::assertSame('Page.md', WikilinkResolver::normalize('/Page'));
        self::assertSame('docs/Page.md', WikilinkResolver::normalize('docs/Page'));
        self::assertSame('docs/Page.md', WikilinkResolver::normalize('docs//Page'));
        self::assertSame('Page.MD', WikilinkResolver::normalize('Page.MD'));
        self::assertNull(WikilinkResolver::normalize(''));
        self::assertNull(WikilinkResolver::normalize('../Page'));
        self::assertNull(WikilinkResolver::normalize('docs/../Page'));
        self::assertNull(WikilinkResolver::normalize('https://example.com/Page'));
        self::assertNull(WikilinkResolver::normalize(str_repeat('a', 600)));
    }

    public function testCandidatesPreferTheDocumentDirectory(): void
    {
        self::assertSame(
            ['docs/sub/Page.md', 'Page.md'],
            WikilinkResolver::candidates('Page', 'docs/sub'),
        );
        self::assertSame(
            ['Page.md'],
            WikilinkResolver::candidates('Page', ''),
        );
    }

    public function testSplit(): void
    {
        self::assertSame(['target' => 'Page', 'label' => null, 'anchor' => null], WikilinkResolver::split('Page'));
        self::assertSame(['target' => 'Page', 'label' => 'Alias', 'anchor' => null], WikilinkResolver::split('Page|Alias'));
        self::assertSame(['target' => 'Page', 'label' => null, 'anchor' => 'Heading'], WikilinkResolver::split('Page#Heading'));
        self::assertSame(
            ['target' => 'Page', 'label' => 'Alias', 'anchor' => 'Heading'],
            WikilinkResolver::split('Page#Heading|Alias'),
        );
        self::assertSame(['target' => '', 'label' => null, 'anchor' => 'Heading'], WikilinkResolver::split('#Heading'));
        self::assertNull(WikilinkResolver::split(''));
        self::assertNull(WikilinkResolver::split('|Alias'));
        self::assertNull(WikilinkResolver::split('   '));
    }

    public function testExtractIgnoresCode(): void
    {
        $markdown = implode("\n", [
            'Intro [[One]] and [[Two|alias]] and [[Three#Anchor]].',
            'Inline `[[NotALink]]` stays code.',
            '```',
            '[[AlsoNot]]',
            '```',
            '~~~',
            '[[NotThisEither]]',
            '~~~',
            'Closing [[One]] again.',
        ]);

        $links = WikilinkResolver::extract($markdown);

        self::assertSame(
            [
                ['target' => 'One', 'anchor' => null],
                ['target' => 'Two', 'anchor' => null],
                ['target' => 'Three', 'anchor' => 'Anchor'],
                ['target' => 'One', 'anchor' => null],
            ],
            $links,
        );
    }

    public function testExtractKeepsLongerFenceClosed(): void
    {
        $markdown = "````\n```\n[[Inside]]\n```\n````\n\n[[Outside]]\n";
        $links = WikilinkResolver::extract($markdown);

        self::assertSame([['target' => 'Outside', 'anchor' => null]], $links);
    }
}
