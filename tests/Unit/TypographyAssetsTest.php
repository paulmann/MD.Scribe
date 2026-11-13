<?php

declare(strict_types=1);

namespace MdViewer\Tests\Unit;

use MdViewer\Tests\TestCase;

/**
 * Guards the two stylesheet contracts that broke a real deployment:
 *
 * 1. `tools/frontend/src/css/typography.css` is *copied* to
 *    `public/assets/css/typography.css` by the build; if the copies differ, the
 *    committed asset no longer reflects the source and the next build silently
 *    reverts a fix.
 * 2. Document tables must fit the reading column. `width: max-content` with
 *    `white-space: nowrap` headers drew tables wider than the column, clipped the
 *    text at the edge and gave no hint that it scrolls — measured on a live
 *    document (896px table in a 736px column).
 */
final class TypographyAssetsTest extends TestCase
{
    private function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private function source(): string
    {
        return (string) file_get_contents($this->root() . '/tools/frontend/src/css/typography.css');
    }

    public function testBuiltCopyMatchesTheSource(): void
    {
        $source = $this->source();
        $built = (string) file_get_contents($this->root() . '/public/assets/css/typography.css');

        self::assertNotSame('', $source);
        self::assertSame(
            hash('sha256', $source),
            hash('sha256', $built),
            'public/assets/css/typography.css must be an exact copy of tools/frontend/src/css/typography.css',
        );
    }

    public function testDocumentTablesFitTheReadingColumn(): void
    {
        $css = $this->source();

        self::assertMatchesRegularExpression(
            '/\.md-body table \{[^}]*width: 100%/s',
            $css,
            'document tables must take the column width, not their unwrapped content width',
        );
        self::assertDoesNotMatchRegularExpression(
            '/\.md-body table \{[^}]*width: max-content/s',
            $css,
            'width: max-content on a document table clips everything wider than the reading column',
        );
    }

    public function testTableHeadersAndCellsWrap(): void
    {
        $css = $this->source();

        self::assertMatchesRegularExpression(
            '/\.md-body thead th \{[^}]*white-space: normal/s',
            $css,
            'header cells must wrap; nowrap forces the table wider than the column',
        );
        self::assertMatchesRegularExpression(
            '/\.md-body tbody td \{[^}]*overflow-wrap: break-word/s',
            $css,
            'long unbreakable tokens must break instead of widening the table',
        );
        self::assertStringContainsString(
            '.md-body .md-table-wrap { margin: 1.5em 0; overflow-x: auto;',
            $css,
            'genuinely wide tables still need the scroll fallback',
        );
    }
}
