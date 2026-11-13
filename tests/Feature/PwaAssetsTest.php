<?php

declare(strict_types=1);

namespace MdViewer\Tests\Feature;

use MdViewer\Tests\TestCase;

/**
 * The offline shell is two static files; nothing in PHP renders them, so a
 * rename or a syntax error would only surface in a browser. These checks keep
 * the pair honest and wired to the endpoints the layout asks for.
 */
final class PwaAssetsTest extends TestCase
{
    public function testManifestIsValidAndPointsAtTheIcon(): void
    {
        $public = dirname(__DIR__, 2) . '/public';
        $raw = (string) @file_get_contents($public . '/manifest.webmanifest');

        self::assertNotSame('', $raw, 'manifest.webmanifest is missing');
        self::assertTrue(json_validate($raw), 'manifest.webmanifest is not valid JSON');

        $manifest = json_decode($raw, true);
        self::assertIsArray($manifest);
        self::assertSame('.', $manifest['start_url'] ?? null);
        self::assertSame('standalone', $manifest['display'] ?? null);
        self::assertNotEmpty($manifest['icons'] ?? []);
        self::assertFileExists($public . '/' . (string) ($manifest['icons'][0]['src'] ?? ''));
    }

    public function testServiceWorkerExistsAndNeverCachesJsonApis(): void
    {
        $script = (string) @file_get_contents(dirname(__DIR__, 2) . '/public/sw.js');

        self::assertNotSame('', $script, 'sw.js is missing');
        self::assertStringContainsString("url.searchParams.has('api')", $script);
        self::assertStringContainsString('mdscribe-shell-v1', $script);
        self::assertStringContainsString("self.addEventListener('fetch'", $script);
    }
}
