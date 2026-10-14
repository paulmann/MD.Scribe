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
