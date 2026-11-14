<?php

declare(strict_types=1);

namespace MdViewer\Tests\Unit;

use MdViewer\Core\ContentSecurityPolicy;
use MdViewer\Tests\TestCase;

/**
 * The CSP header is the one security control that is read by the browser and never
 * by the test suite, so it is exactly where a duplicate or a silently dropped source
 * survives. Production served `img-src 'self' blob: data: https: data:` with
 * external images enabled — harmless for a browser, invisible to every check, and a
 * sign that the policy was assembled by concatenation.
 */
final class ContentSecurityPolicyTest extends TestCase
{
    /** @return list<array{bool, bool}> */
    private static function switches(): array
    {
        return [[false, false], [true, false], [false, true], [true, true]];
    }

    public function testNoDirectiveRepeatsASource(): void
    {
        foreach (self::switches() as [$images, $embeds]) {
            $policy = ContentSecurityPolicy::directives('abc123', $images, $embeds);
            foreach ($policy as $directive => $sources) {
                self::assertSame(
                    array_values(array_unique($sources)),
                    $sources,
                    $directive . ' repeats a source (images=' . var_export($images, true)
                    . ', embeds=' . var_export($embeds, true) . ')',
                );
            }
        }
    }

    public function testExternalImagesAddHttpsToImgSrcOnly(): void
    {
        $off = ContentSecurityPolicy::directives('n', false, false);
        $on = ContentSecurityPolicy::directives('n', true, false);

        self::assertSame(["'self'", 'blob:', 'data:'], $off['img-src']);
        self::assertSame(["'self'", 'blob:', 'data:', 'https:'], $on['img-src']);
        self::assertSame(["'self'"], $on['frame-src'], 'images must not widen frames');
        self::assertSame(["'self'"], $on['media-src'], 'images must not widen media');
    }

    public function testEmbedsWidenFramesAndMediaButNotImages(): void
    {
        $policy = ContentSecurityPolicy::directives('n', false, true);

        self::assertSame(["'self'", 'https:'], $policy['frame-src']);
        self::assertSame(["'self'", 'https:'], $policy['media-src']);
        self::assertSame(["'self'", 'blob:', 'data:'], $policy['img-src']);
    }

    public function testTheHeaderCarriesTheNonceAndTheFixedDirectives(): void
    {
        $header = ContentSecurityPolicy::header('abc123', false, false);

        self::assertStringContainsString("script-src 'self' 'nonce-abc123'", $header);
        self::assertStringContainsString("object-src 'none'", $header);
        self::assertStringContainsString("frame-ancestors 'self'", $header);
        self::assertStringContainsString("base-uri 'self'", $header);
        self::assertStringContainsString("form-action 'self'", $header);
        self::assertStringContainsString("style-src 'self' 'unsafe-inline'", $header);
        self::assertStringNotContainsString('https:', $header);
    }

    /** The regression itself: the directive that carried `data:` twice. */
    public function testImgSrcCarriesDataExactlyOnce(): void
    {
        $header = ContentSecurityPolicy::header('nonce', true, true);
        $directive = '';
        foreach (explode('; ', $header) as $part) {
            if (str_starts_with($part, 'img-src ')) {
                $directive = $part;
            }
        }

        self::assertNotSame('', $directive, 'the policy must have an img-src directive');
        self::assertSame(1, substr_count($directive, 'data:'), 'img-src: ' . $directive);
        self::assertSame("img-src 'self' blob: data: https:", $directive);
    }
}
