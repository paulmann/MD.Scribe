<?php

declare(strict_types=1);

namespace MdViewer\Tests\Unit;

use MdViewer\Tests\TestCase;
use MdViewer\Tools\ReleaseBuilder;

require_once dirname(__DIR__, 2) . '/tools/lib/ReleaseBuilder.php';

/**
 * The release builder's checks, without git or Composer.
 *
 * Both real failures of this project's release path were "the archive is not what
 * the manifest describes": one archive had no vendor/, another was wrapped in
 * MD.Scribe-<version>/ instead of being flat. Neither needed a network to catch —
 * they needed the tree compared with the manifest that travels with it, which is
 * exactly what verifyAgainstManifest() does.
 */
final class ReleaseBuilderTest extends TestCase
{
    /** Build a small tree: a manifest, one shipped file, and the excluded kinds. */
    private function tree(): string
    {
        $root = $this->tmpRoot . '/release';
        @mkdir($root . '/config', 0o777, true);
        @mkdir($root . '/src/Core', 0o777, true);
        @mkdir($root . '/storage/backups', 0o777, true);
        @mkdir($root . '/content', 0o777, true);
        @mkdir($root . '/tests', 0o777, true);
        @mkdir($root . '/.git', 0o777, true);
        @mkdir($root . '/tools/frontend/node_modules/pkg', 0o777, true);

        file_put_contents($root . '/src/Payload.php', "<?php // payload\n");
        file_put_contents($root . '/storage/.htaccess', "Require all denied\n");
        file_put_contents($root . '/storage/backups/app-1.json', "{}\n");
        file_put_contents($root . '/content/index.md', "# Home\n");
        file_put_contents($root . '/tests/SmokeTest.php', "<?php\n");
        file_put_contents($root . '/.git/HEAD', "ref: refs/heads/main\n");
        file_put_contents($root . '/tools/frontend/node_modules/pkg/index.js', "module.exports = 1;\n");
        file_put_contents($root . '/.md.ini', "SITE_NAME = Local\n");
        $versionBody = "<?php\nfinal class Version\n{\n    public const string VERSION = '9.9.9';\n    public const string CODENAME = 'Probe';\n}\n";
        file_put_contents($root . '/src/Core/Version.php', $versionBody);

        $manifest = [
            'version' => '9.9.9',
            'files' => [
                ['path' => 'src/Payload.php', 'sha256' => hash('sha256', "<?php // payload\n")],
                ['path' => 'src/Core/Version.php', 'sha256' => hash('sha256', $versionBody)],
            ],
        ];
        file_put_contents($root . '/config/manifest.json', (string) json_encode($manifest));

        return $root;
    }

    public function testCollectShippedFilesRespectsTheExclusions(): void
    {
        $files = ReleaseBuilder::collectShippedFiles($this->tree());

        self::assertContains('src/Payload.php', $files);
        self::assertContains('storage/.htaccess', $files, 'the access rule travels with a release');
        self::assertContains('content/index.md', $files);
        self::assertContains('tests/SmokeTest.php', $files);
        self::assertNotContains('storage/backups/app-1.json', $files);
        self::assertNotContains('.md.ini', $files);
        self::assertNotContains('.git/HEAD', $files);
        self::assertNotContains('tools/frontend/node_modules/pkg/index.js', $files);
        // Sorted, because the archive order must not depend on directory order.
        $sorted = $files;
        sort($sorted, SORT_STRING);
        self::assertSame($sorted, $files);
    }

    public function testVerifyAcceptsATreeThatMatchesItsManifest(): void
    {
        $result = ReleaseBuilder::verifyAgainstManifest($this->tree(), (array) ReleaseBuilder::readManifest($this->tree()));

        self::assertTrue($result['ok'], implode('; ', $result['problems']));
        self::assertSame(2, $result['checked']);
        self::assertSame([], $result['unexplained']);
        // content/, storage/ and tests/ are excluded by design and counted, not failed.
        self::assertArrayHasKey('content/', $result['outside']);
        self::assertArrayHasKey('tests/', $result['outside']);
    }

    /**
     * The negative test the release checklist demands: after the manifest is built,
     * one shipped file changes. The builder must refuse and name that file.
     */
    public function testVerifyRefusesAFileChangedAfterTheManifestWasBuilt(): void
    {
        $root = $this->tree();
        file_put_contents($root . '/src/Payload.php', "<?php // changed after the manifest was built\n");

        $result = ReleaseBuilder::verifyAgainstManifest($root, (array) ReleaseBuilder::readManifest($root));

        self::assertFalse($result['ok']);
        self::assertContains('differs from the manifest: src/Payload.php', $result['problems']);
    }

    public function testVerifyRefusesAMissingFile(): void
    {
        $root = $this->tree();
        unlink($root . '/src/Payload.php');

        $result = ReleaseBuilder::verifyAgainstManifest($root, (array) ReleaseBuilder::readManifest($root));

        self::assertFalse($result['ok']);
        self::assertContains('missing: src/Payload.php', $result['problems']);
    }

    public function testVerifyRefusesAShippedFileTheManifestDoesNotMention(): void
    {
        $root = $this->tree();
        file_put_contents($root . '/src/Stray.php', "<?php // not in the manifest\n");

        $result = ReleaseBuilder::verifyAgainstManifest($root, (array) ReleaseBuilder::readManifest($root));

        self::assertFalse($result['ok']);
        self::assertContains('not in the manifest and not excluded by design: src/Stray.php', $result['problems']);
        self::assertContains('src/Stray.php', $result['unexplained']);
    }

    public function testExclusionReasonsAreNamed(): void
    {
        self::assertSame('config/manifest.json', ReleaseBuilder::exclusionReason('config/manifest.json'));
        self::assertSame('content/', ReleaseBuilder::exclusionReason('content/deep/page.md'));
        self::assertSame('tests/', ReleaseBuilder::exclusionReason('tests/Unit/Foo.php'));
        self::assertSame('storage/', ReleaseBuilder::exclusionReason('storage/cache/x.json'));
        self::assertNull(ReleaseBuilder::exclusionReason('src/App.php'));
    }

    /**
     * Two questions, two predicates. A manifest generated in a working tree listed
     * `.work/`, `.wiki/`, `.zwork/` and 1 GB of `.ragraf/` (5 139 entries), and would
     * have listed content/ and tests/ as well — the first set exists only locally, the
     * second must never be replaced by an update.
     */
    public function testTheManifestDescribesFewerFilesThanTheArchive(): void
    {
        // In the archive and in the manifest.
        self::assertTrue(ReleaseBuilder::ships('src/Payload.php'));
        self::assertTrue(ReleaseBuilder::inManifest('src/Payload.php'));
        self::assertTrue(ReleaseBuilder::inManifest('deploy/nginx.conf'));

        // In the archive, deliberately absent from the manifest.
        self::assertTrue(ReleaseBuilder::ships('content/index.md'));
        self::assertFalse(ReleaseBuilder::inManifest('content/index.md'));
        self::assertTrue(ReleaseBuilder::ships('tests/SmokeTest.php'));
        self::assertFalse(ReleaseBuilder::inManifest('tests/SmokeTest.php'));

        // In neither: scratch directories that exist only on a working machine.
        foreach (['.ragraf/index/lex/lexvec.sqlite', '.work/probe.py', '.wiki/Home.md',
                  '.zwork/attachments/a.txt', '.git/HEAD', 'node_modules/x/index.js',
                  '.phpunit.cache/test-results'] as $rel) {
            self::assertFalse(ReleaseBuilder::ships($rel), $rel . ' must not ship');
            self::assertFalse(ReleaseBuilder::inManifest($rel), $rel . ' must not be in the manifest');
        }
    }

    public function testVersionsComeFromTheVersionFile(): void
    {
        $root = $this->tree();
        file_put_contents(
            $root . '/src/Core/Version.php',
            "<?php\nfinal class Version\n{\n    public const string VERSION = '9.9.9';\n    public const string CODENAME = 'Probe';\n}\n",
        );

        self::assertSame('9.9.9', ReleaseBuilder::versionFromSource($root));
        self::assertSame('Probe', ReleaseBuilder::codenameFromSource($root));
    }

    /**
     * Two writes of the same tree must produce the same bytes: the archive digest
     * is quoted in a release, so it cannot depend on when it was built.
     */
    public function testTheArchiveIsFlatAndReproducible(): void
    {
        $root = $this->tree();
        ReleaseBuilder::verifyAgainstManifest($root, (array) ReleaseBuilder::readManifest($root));
        $files = ReleaseBuilder::collectShippedFiles($root);
        $pinned = 1794658140;

        $first = ReleaseBuilder::writeArchive($root, $files, $this->tmpRoot . '/out/one.zip', $pinned);
        $second = ReleaseBuilder::writeArchive($root, $files, $this->tmpRoot . '/out/two.zip', $pinned);

        self::assertSame($first['sha256'], $second['sha256'], 'same tree and timestamp, same bytes');
        self::assertSame(count($files), $first['entries']);

        // The timestamp is part of the artifact on purpose: it is pinned to the commit,
        // so a different pin must produce a different archive rather than silently the
        // same one. DOS timestamps have two-second resolution, so step by two.
        $other = ReleaseBuilder::writeArchive($root, $files, $this->tmpRoot . '/out/three.zip', $pinned + 2);
        self::assertNotSame($first['sha256'], $other['sha256']);

        // ... and one second is inside the same DOS tick, which is why the pin is the
        // commit time rather than anything finer.
        $sameTick = ReleaseBuilder::writeArchive($root, $files, $this->tmpRoot . '/out/four.zip', $pinned + 1);
        self::assertSame($first['sha256'], $sameTick['sha256']);

        $zip = new \ZipArchive();
        self::assertTrue($zip->open($first['path']), 'the archive is a readable zip');
        $names = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $names[] = (string) $zip->getNameIndex($i);
        }
        $zip->close();

        self::assertSame($files, $names, 'flat: entry names are the relative paths');
        self::assertContains('config/manifest.json', $names);
        foreach ($names as $name) {
            self::assertStringNotContainsString('\\', $name);
            self::assertStringStartsNotWith('/', $name);
            self::assertStringNotContainsString('..', $name);
        }
    }
}
