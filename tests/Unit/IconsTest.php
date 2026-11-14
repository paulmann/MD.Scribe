<?php

declare(strict_types=1);

namespace MdViewer\Tests\Unit;

use MdViewer\Tests\TestCase;
use MdViewer\View\Icons;

/**
 * Guards the generated icon map against the two build paths drifting apart.
 *
 * `node build.mjs --vendor` and `node generate-icons.mjs` both write
 * src/View/Icons.php. Only the second one used to add the `circle-alert`
 * fallback that `Icons::svg()` leans on, so a `--vendor` rebuild (the release
 * checklist step) produced a class whose fallback was missing: an unknown icon
 * name then raised "Undefined array key" and rendered an empty <svg>. PHPUnit
 * does not fail on that unless something renders an unknown name, so the
 * regression reached main once (lucide-static 0.577 refresh, 2026-11-14).
 */
final class IconsTest extends TestCase
{
    public function testFallbackIconIsInTheMap(): void
    {
        self::assertTrue(Icons::has('circle-alert'), 'the fallback icon must be part of the generated map');
        self::assertNotSame('', Icons::all()['circle-alert'], 'the fallback icon must carry markup');
    }

    public function testUnknownNameRendersAnIconInsteadOfAnEmptySvg(): void
    {
        $svg = Icons::svg('no-such-icon-name');

        // An empty fallback yields the wrapper with nothing inside it, and the
        // assertion below is what catches exactly that. The inner markup starts
        // with lucide's license comment, so a comment counts as content.
        self::assertMatchesRegularExpression('/<svg[^>]*>\s*<(?:!--|[a-z])/', $svg);
    }

    public function testKnownIconRendersItsOwnMarkup(): void
    {
        self::assertTrue(Icons::has('info'));
        self::assertStringContainsString('<svg', Icons::svg('info'));
    }
}
