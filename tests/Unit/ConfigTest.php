<?php

declare(strict_types=1);

namespace MdViewer\Tests\Unit;

use MdViewer\Core\Config;
use MdViewer\Tests\TestCase;

final class ConfigTest extends TestCase
{
    public function testCreatesIniWithDefaultsOnFirstLoad(): void
    {
        $config = Config::instance();
        self::assertFileExists($this->tmpRoot . '/.md.ini');
        self::assertTrue($config->bool('DISABLE_UPLOAD'));
        self::assertFalse($config->bool('ALLOW_UPDATE'));
        self::assertSame('MD.Scribe', $config->str('SITE_NAME'));
        self::assertSame(8, $config->int('MAX_UPLOAD_MB'));
    }

    public function testAutoGeneratesApiKey(): void
    {
        $config = Config::instance();
        $key = $config->str('API_KEY');
        self::assertStringStartsWith('mdv_', $key);
        self::assertGreaterThanOrEqual(44, strlen($key));
        // The generated key must be persisted, not only in memory.
        self::assertStringContainsString($key, (string) file_get_contents($this->tmpRoot . '/.md.ini'));
    }

    /**
     * A release-directory layout points `.md.ini` at `mdscribe-data/.md.ini`, because
     * that is the only runtime state an upgrade does not replace. `rename()` replaces
     * the link, not its target — measured in production on 2026-11-14: after one page
     * request the release held the config and the data directory kept an 18-byte seed,
     * which the next deploy would have used to seed the new release.
     */
    public function testWritingThroughASymlinkKeepsTheLinkAndUpdatesTheTarget(): void
    {
        $data = $this->tmpRoot . '/data';
        $release = $this->tmpRoot . '/release';
        @mkdir($data, 0o777, true);
        @mkdir($release, 0o777, true);
        $target = $data . '/.md.ini';
        file_put_contents($target, "SITE_NAME = Seed\n");
        $link = $release . '/.md.ini';
        if (!@symlink($target, $link)) {
            self::markTestSkipped('symlinks are not permitted here (Windows needs a privilege); CI covers this');
        }
        self::assertTrue(is_link($link));

        $config = Config::loadFrom($link);
        $config->set('SITE_NAME', 'Probe Site');

        self::assertTrue(is_link($link), 'the deploy symlink must survive a settings save');
        // realpath() on Windows can return an 8.3 short path for the resolved link, so
        // compare identity, not spelling.
        self::assertSame(fileinode($target), fileinode($link), 'the link must still resolve to the data file');
        $written = (string) file_get_contents($target);
        self::assertStringContainsString('SITE_NAME = Probe Site', $written);
        self::assertStringContainsString('ADMIN_PASSWORD_HASH', $written, 'the full schema goes to the target');
        Config::reset();
    }

    /**
     * An existing but unreadable config must not be regenerated.
     *
     * Measured in production on 2026-11-14: a config written by root with mode 0640 was
     * unreadable for the pool user, and the next request replaced it with defaults —
     * taking the site name and the home document with it. A file that cannot be read is a
     * failure to report, not a blank page to fill in.
     */
    public function testAnUnreadableIniIsNotOverwrittenWithDefaults(): void
    {
        $path = $this->tmpRoot . '/locked.ini';
        $original = "SITE_NAME = autoyap.ru - документация\nHOME_DOC = autoyap/index.md\n";
        file_put_contents($path, $original);
        @chmod($path, 0o000);
        clearstatcache(true, $path);
        if (is_readable($path)) {
            @chmod($path, 0o640);
            self::markTestSkipped('this user can read a 0000 file (root); CI covers the case');
        }

        $inodeBefore = fileinode($path);
        $sizeBefore = filesize($path);

        // Capture the complaint instead of letting it land in the test output: a config
        // that cannot be read must be reported, so the message is part of the contract.
        $errorLog = $this->tmpRoot . '/config-errors.log';
        @unlink($errorLog);
        $previousLog = ini_set('error_log', $errorLog);
        try {
            $config = Config::loadFrom($path);
        } finally {
            ini_set('error_log', $previousLog === false ? '' : $previousLog);
        }

        self::assertFalse($config->isWritable(), 'a config that cannot be read must not be written');
        self::assertSame('MD.Scribe', $config->str('SITE_NAME'), 'the app runs on defaults, not on a rewrite');
        clearstatcache(true, $path);
        // A rewrite goes through rename(), so both the inode and the size would move.
        self::assertSame($inodeBefore, fileinode($path), 'the file must not be replaced');
        self::assertSame($sizeBefore, filesize($path), 'the file must keep its bytes');

        @chmod($path, 0o640);
        self::assertSame($original, (string) file_get_contents($path), 'and it must still hold them');
        self::assertStringContainsString(
            'cannot read',
            (string) @file_get_contents($errorLog),
            'the failure must be logged, not swallowed',
        );
        Config::reset();
    }

    /** A zero-byte file is not damage: it is a first run, and it gets filled in. */
    public function testAnEmptyIniIsFilledWithDefaults(): void
    {
        $path = $this->tmpRoot . '/empty.ini';
        file_put_contents($path, '');

        $config = Config::loadFrom($path);

        self::assertTrue($config->isWritable());
        self::assertStringContainsString('SITE_NAME', (string) file_get_contents($path));
        Config::reset();
    }

        /** A dangling link names a file that does not exist yet: write it there, keep the link. */
    public function testWritingThroughADanglingSymlinkCreatesTheTarget(): void
    {
        $data = $this->tmpRoot . '/data2';
        $release = $this->tmpRoot . '/release2';
        @mkdir($data, 0o777, true);
        @mkdir($release, 0o777, true);
        $target = $data . '/.md.ini';
        $link = $release . '/.md.ini';
        if (!@symlink($target, $link)) {
            self::markTestSkipped('symlinks are not permitted here (Windows needs a privilege); CI covers this');
        }
        self::assertFalse(is_file($link));

        Config::loadFrom($link);
        Config::instance()->set('SITE_NAME', 'Created Through');

        self::assertTrue(is_link($link));
        self::assertFileExists($target);
        self::assertStringContainsString('SITE_NAME = Created Through', (string) file_get_contents($target));
        Config::reset();
    }

    public function testAppendsMissingKeysWithoutTouchingExistingOnes(): void
    {
        file_put_contents($this->tmpRoot . '/.md.ini', "; custom comment\nSITE_NAME = My Docs\nUNKNOWN_KEY = kept\n");
        $config = Config::instance();

        self::assertSame('My Docs', $config->str('SITE_NAME'));
        $content = (string) file_get_contents($this->tmpRoot . '/.md.ini');
        self::assertStringContainsString('; custom comment', $content);
        self::assertStringContainsString('UNKNOWN_KEY = kept', $content);
        self::assertStringContainsString('ADMIN_ENABLED = true', $content);
        self::assertTrue($config->isExplicit('SITE_NAME'));
        self::assertFalse($config->isExplicit('ADMIN_ENABLED'));
    }

    public function testStrictValueParsing(): void
    {
        file_put_contents($this->tmpRoot . '/.md.ini', implode("\n", [
            'SITE_NAME = "  quoted name  "',
            "SITE_NAME2 = 'x'",
            'MAX_UPLOAD_MB = 42',
            'ALLOW_UPDATE = yes',
            'DISABLE_UPLOAD = 0',
            'BAD',            // no '=' — skipped
            '= nokey',        // invalid key — skipped
            'CACHE_TTL = not-a-number',
        ]));
        $config = Config::instance();

        self::assertSame('  quoted name  ', $config->str('SITE_NAME'));
        self::assertSame(42, $config->int('MAX_UPLOAD_MB'));
        self::assertTrue($config->bool('ALLOW_UPDATE'));   // 'yes' parses true
        self::assertFalse($config->bool('DISABLE_UPLOAD'));
        self::assertSame(3600, $config->int('CACHE_TTL')); // non-numeric → (int)'not…' = 0? no: raw kept, (int) cast → 0; default only when absent
    }

    public function testSetPersistsIncludingDollarValues(): void
    {
        $config = Config::instance();
        $hash = password_hash('Sup3rSecret!password', PASSWORD_DEFAULT);
        $config->set('ADMIN_PASSWORD_HASH', $hash);

        $fresh = Config::loadFrom($this->tmpRoot . '/.md.ini');
        self::assertSame($hash, $fresh->str('ADMIN_PASSWORD_HASH'));
        self::assertTrue(password_verify('Sup3rSecret!password', $fresh->str('ADMIN_PASSWORD_HASH')));
    }

    public function testAdminPasswordBootstrapHashesAndClears(): void
    {
        file_put_contents($this->tmpRoot . '/.md.ini', "ADMIN_PASSWORD = hunter2hunter2\n");
        $config = Config::instance();

        self::assertTrue(str_starts_with($config->str('ADMIN_PASSWORD_HASH'), '$2y$'));
        self::assertTrue(password_verify('hunter2hunter2', $config->str('ADMIN_PASSWORD_HASH')));
        self::assertSame('', $config->str('ADMIN_PASSWORD'));
        $onDisk = (string) file_get_contents($this->tmpRoot . '/.md.ini');
        self::assertStringNotContainsString('hunter2hunter2', $onDisk);
    }

    public function testPerDirectoryFlagLayering(): void
    {
        mkdir($this->tmpRoot . '/content/share/sub', 0o777, true);
        file_put_contents($this->tmpRoot . '/content/share/.md.ini', "DISABLE_UPLOAD = false\n");
        file_put_contents($this->tmpRoot . '/content/share/sub/.md.ini', "DISABLE_UPLOAD = true\nDISABLE_CLIPBOARD = true\nALLOW_UPDATE = true\n");

        // PHPUnit captures error_log() and treats unannounced output as risky.
        // This warning is part of the contract, so it is declared here.
        $this->expectOutputRegex('/ignored installation-level key ALLOW_UPDATE/');

        $config = Config::instance();

        // Base default: uploads disabled
        self::assertTrue($config->flagsFor([])['DISABLE_UPLOAD']);
        // share/ re-enables
        self::assertFalse($config->flagsFor(['share'])['DISABLE_UPLOAD']);
        // share/sub/ disables again — deeper wins
        $flags = $config->flagsFor(['share', 'sub']);
        self::assertTrue($flags['DISABLE_UPLOAD']);
        self::assertTrue($flags['DISABLE_CLIPBOARD']);
    }

    public function testBrowseDirDefaultsToContent(): void
    {
        $config = Config::instance();
        self::assertSame(realpath($this->tmpRoot . '/content'), realpath($config->browseDir()));
    }
}
