<?php

declare(strict_types=1);

namespace MdViewer\Tests\Feature;

use MdViewer\Admin\UpdateService;
use MdViewer\Core\HttpClient;
use MdViewer\Core\Logger;
use MdViewer\Tests\TestCase;

/**
 * The updater's dry run must judge the *release*, not the local manifest.
 *
 * A report that only compares files with the manifest they came from always says
 * "nothing to replace" — a green answer about the wrong object. These tests build
 * a real zip, hand it to the service through a substituted HttpClient and check
 * what plan() says about files that match and files that do not.
 *
 * No network: the fake client ignores the URL and answers with local bytes.
 */
final class UpdatePlanTest extends TestCase
{
    private string $assetPath = '';

    protected function tearDown(): void
    {
        if ($this->assetPath !== '' && is_file($this->assetPath)) {
            @unlink($this->assetPath);
        }
        parent::tearDown();
    }

    /**
     * Build a release archive: manifest + one payload file whose sha256 is known.
     *
     * @return array{zip: string, manifestVersion: string, payloadSha: string}
     */
    private function buildRelease(): array
    {
        $payload = "# Release payload\n\nbody\n";
        $payloadSha = hash('sha256', $payload);
        $manifest = [
            'version' => '9.9.9',
            'files' => [
                ['path' => 'src/Payload.php', 'sha256' => $payloadSha, 'version' => '9.9.9'],
            ],
        ];

        $stage = $this->tmpRoot . '/stage';
        @mkdir($stage . '/config', 0o777, true);
        @mkdir($stage . '/src', 0o777, true);
        file_put_contents($stage . '/config/manifest.json', (string) json_encode($manifest));
        file_put_contents($stage . '/src/Payload.php', $payload);

        $this->assetPath = $this->tmpRoot . '/release.zip';
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($this->assetPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE));
        $zip->addFile($stage . '/config/manifest.json', 'config/manifest.json');
        $zip->addFile($stage . '/src/Payload.php', 'src/Payload.php');
        $zip->close();

        return ['zip' => $this->assetPath, 'manifestVersion' => '9.9.9', 'payloadSha' => $payloadSha];
    }

    private function service(string $zipPath): UpdateService
    {
        $bytes = (string) file_get_contents($zipPath);
        $http = new class ($bytes) extends HttpClient {
            public function __construct(private readonly string $bytes)
            {
            }

            public function get(string $url, ?string $ifNoneMatch = null, array $headers = []): array
            {
                return ['status' => 200, 'body' => $this->bytes, 'etag' => null, 'error' => null];
            }

            public function getJson(string $url, array $headers = []): array
            {
                return [
                    'status' => 200,
                    'error' => null,
                    'data' => [
                        'tag_name' => 'v9.9.9',
                        'assets' => [[
                            'name' => 'MD.Scribe-9.9.9.zip',
                            'browser_download_url' => 'https://example.invalid/MD.Scribe-9.9.9.zip',
                        ]],
                    ],
                ];
            }
        };

        return new UpdateService($this->config(), new Logger($this->config(), $this->tmpRoot . '/storage'), null, $http);
    }

    public function testPlanReportsWhatTheReleaseWouldReplace(): void
    {
        $release = $this->buildRelease();
        $plan = $this->service($release['zip'])->plan();

        self::assertTrue($plan['ok'] ?? false, (string) ($plan['error'] ?? ''));
        self::assertSame('9.9.9', $plan['to']);
        self::assertSame('MD.Scribe-9.9.9.zip', $plan['asset']);
        self::assertSame(1, $plan['verified']);
        self::assertSame(['src/Payload.php'], $plan['replace']);
        self::assertSame(0, $plan['unchanged']);
        self::assertSame(0, $plan['conflicts'], 'the file does not exist locally yet');
    }

    public function testPlanSeesAnIdenticalFileAsUnchanged(): void
    {
        $release = $this->buildRelease();
        @mkdir($this->tmpRoot . '/src', 0o777, true);
        file_put_contents($this->tmpRoot . '/src/Payload.php', "# Release payload\n\nbody\n");

        $plan = $this->service($release['zip'])->plan();

        self::assertTrue($plan['ok'] ?? false);
        self::assertSame([], $plan['replace']);
        self::assertSame(1, $plan['unchanged']);
    }

    public function testPlanFlagsALocallyModifiedFileAsAConflict(): void
    {
        $release = $this->buildRelease();
        @mkdir($this->tmpRoot . '/src', 0o777, true);
        file_put_contents($this->tmpRoot . '/src/Payload.php', "locally edited\n");

        $plan = $this->service($release['zip'])->plan();

        self::assertTrue($plan['ok'] ?? false);
        self::assertSame(['src/Payload.php'], $plan['replace']);
        self::assertSame(1, $plan['conflicts']);
    }

    public function testPlanFailsWhenTheArchiveDoesNotMatchItsManifest(): void
    {
        $release = $this->buildRelease();
        // Rewrite the payload inside the zip: the manifest still names the old hash.
        $zip = new \ZipArchive();
        self::assertTrue($zip->open($release['zip']));
        $zip->addFromString('src/Payload.php', "tampered\n");
        $zip->close();

        $plan = $this->service($release['zip'])->plan();

        self::assertFalse($plan['ok'] ?? true);
        self::assertStringContainsString('Integrity check failed', (string) ($plan['error'] ?? ''));
    }

    public function testPlanWritesNothingIntoTheProject(): void
    {
        $release = $this->buildRelease();
        $before = $this->snapshot($this->tmpRoot . '/src');

        $this->service($release['zip'])->plan();

        self::assertSame($before, $this->snapshot($this->tmpRoot . '/src'));
        self::assertDirectoryDoesNotExist($this->tmpRoot . '/src/Payload.php');
    }

    /** @return array<string, string> */
    private function snapshot(string $dir): array
    {
        $out = [];
        foreach (glob($dir . '/*') ?: [] as $path) {
            $out[basename($path)] = is_file($path) ? (string) file_get_contents($path) : 'dir';
        }

        return $out;
    }
}
