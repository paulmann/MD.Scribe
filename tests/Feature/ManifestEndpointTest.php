<?php

declare(strict_types=1);

namespace MdViewer\Tests\Feature;

use MdViewer\Core\Manifest;
use MdViewer\Core\Response;
use MdViewer\Tests\TestCase;

/**
 * The web app manifest is built and served by the application, not as a static
 * file.
 *
 * Why this test exists: `.webmanifest` is missing from most `mime.types` tables,
 * so a static file arrives as `application/octet-stream`. The usual server-side
 * fix — a `types { }` block — replaces the whole MIME table, and on a real host
 * that turned every css/js into `application/octet-stream` and rendered the site
 * unstyled. Serving it from PHP removes that dependency; these assertions pin the
 * media type, the document shape, the sub-directory scope and the wiring.
 */
final class ManifestEndpointTest extends TestCase
{
    public function testResponseCarriesTheManifestMediaType(): void
    {
        $response = Response::manifest('{"name":"x"}');

        self::assertSame(
            'application/manifest+json; charset=utf-8',
            $response->headers()['Content-Type'] ?? '',
            'the manifest needs a JSON media type; the server cannot be trusted with it',
        );
        self::assertSame('nosniff', $response->headers()['X-Content-Type-Options'] ?? '');
    }

    public function testDocumentDescribesAnInstallableApp(): void
    {
        $manifest = Manifest::build('autoyap.ru - документация', '/docs', '/docs/assets/img/favicon.svg');

        self::assertSame('standalone', $manifest['display']);
        self::assertNotSame('', (string) $manifest['name']);
        self::assertSame('/docs/', $manifest['scope']);
        self::assertSame('/docs/', $manifest['start_url']);
        self::assertSame('image/svg+xml', $manifest['icons'][0]['type']);
        self::assertSame('/docs/assets/img/favicon.svg', $manifest['icons'][0]['src']);
        self::assertTrue(json_validate((string) json_encode($manifest)));
    }

    public function testRootInstallUsesARelativeScope(): void
    {
        $manifest = Manifest::build('MD.Scribe', '', '/assets/img/favicon.svg');

        self::assertSame('./', $manifest['scope'], 'a relative scope keeps the manifest host-agnostic');
        self::assertSame('./', $manifest['start_url']);
    }

    public function testEmptySiteNameFallsBack(): void
    {
        self::assertSame('MD.Scribe', Manifest::build('   ', '', '/icon.svg')['name']);
    }

    public function testRouteAndLayoutAreWired(): void
    {
        $front = (string) file_get_contents(dirname(__DIR__, 2) . '/src/Http/FrontController.php');
        self::assertStringContainsString("'manifest' => \$controller->manifest()", $front);

        $layout = (string) file_get_contents(dirname(__DIR__, 2) . '/templates/layout/app.php');
        self::assertStringContainsString("\$req->url(['api' => 'manifest'])", $layout);
        self::assertStringNotContainsString(
            "\$req->assetUrl('manifest.webmanifest')",
            $layout,
            'linking the static file brings the MIME problem back on hosts without a mapping',
        );
    }
}
