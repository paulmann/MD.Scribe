<?php

declare(strict_types=1);

namespace MdViewer\Tests;

use MdViewer\Core\Config;
use MdViewer\Core\Paths;
use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base test case: every test runs against a throw-away project root so no
 * test can touch the real storage, content or .md.ini.
 */
abstract class TestCase extends BaseTestCase
{
    protected string $tmpRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpRoot = sys_get_temp_dir() . '/mdv-test-' . bin2hex(random_bytes(6));
        mkdir($this->tmpRoot . '/config', 0o777, true);
        mkdir($this->tmpRoot . '/content', 0o777, true);
        mkdir($this->tmpRoot . '/storage', 0o777, true);
        copy(dirname(__DIR__) . '/config/defaults.php', $this->tmpRoot . '/config/defaults.php');

        Paths::setRoot($this->tmpRoot);
        Config::reset();
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->tmpRoot);
        Config::reset();
        Paths::setRoot(dirname(__DIR__));
        parent::tearDown();
    }

    protected function config(array $overrides = []): Config
    {
        $config = Config::instance();
        foreach ($overrides as $key => $value) {
            $config->set($key, $value);
        }

        return $config;
    }

    protected function writeContent(string $relative, string $content): string
    {
        $path = $this->tmpRoot . '/content/' . ltrim($relative, '/');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0o777, true);
        }
        file_put_contents($path, $content);

        return $path;
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($dir);
    }
}
