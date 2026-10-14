<?php

/**
 * MD.Viewer 3.0 — the document repository.
 *
 * Owns everything about *where documents live*: the browse root resolution,
 * the recursive scanner (with safety caps), path validation delegation,
 * lightweight metadata for the file browser and prev/next navigation.
 */

declare(strict_types=1);

namespace MdViewer\Files;

use MdViewer\Core\Config;
use MdViewer\Core\Logger;

final class MarkdownRepository
{
    /** Directories never scanned (caches, deps, backups, VCS). */
    private const array SKIP_DIRS = [
        'node_modules', 'vendor', 'md.backup', 'storage', '.git', '.svn', '.hg',
        '.idea', '.vscode', '.next', '.cache', 'dist', 'build',
    ];

    private PathValidator $validator;
    private ?string $browseDir = null;

    public function __construct(
        private readonly Config $config,
        private readonly Logger $logger = new Logger(),
    ) {
        $this->validator = new PathValidator(max(1, $config->int('MAX_SCAN_DEPTH')));
    }

    public function validator(): PathValidator
    {
        return $this->validator;
    }

    public function browseDir(): string
    {
        return $this->browseDir ??= $this->config->browseDir();
    }

    /**
     * Resolve a user-supplied `?file=` parameter to an absolute path.
     */
    public function resolve(string $file): ?string
    {
        return $this->validator->validateFile($file, $this->browseDir());
    }

    /** Absolute path for a validated relative path, or null. */
    public function absolute(string $relative): ?string
    {
        return $this->resolve($relative);
    }

    /** Read a document, stripping BOM and normalizing newlines. */
    public static function read(string $absolutePath): ?string
    {
        $content = @file_get_contents($absolutePath);
        if ($content === false) {
            return null;
        }
        $content = str_replace(["\r\n", "\r"], "\n", $content);
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        return $content;
    }

    /**
     * Recursive scan of the browse root.
     *
     * @return list<array{path: string, title: string, size: int, modified: int, depth: int}>
     */
    public function scan(?string $subDir = null): array
    {
        $root = $this->browseDir();
        $base = $root;
        if ($subDir !== null && $subDir !== '') {
            $resolved = $this->validator->validateDir($subDir, $root);
            if ($resolved === null) {
                return [];
            }
            $base = $resolved;
        }

        $maxDepth = max(1, $this->config->int('MAX_SCAN_DEPTH'));
        $maxFiles = max(10, $this->config->int('MAX_FILES_SCAN'));

        $files = [];
        $stack = [[$base, 0]];
        while ($stack !== []) {
            [$dir, $depth] = array_pop($stack);
            if ($depth > $maxDepth) {
                continue;
            }
            $entries = @scandir($dir);
            if ($entries === false) {
                $this->logger->warning('scan: unreadable directory', ['dir' => $dir]);
                continue;
            }
            foreach ($entries as $entry) {
                if ($entry === '.' || $entry === '..') {
                    continue;
                }
                $full = $dir . DIRECTORY_SEPARATOR . $entry;
                if (is_dir($full)) {
                    if (str_starts_with($entry, '.') || in_array($entry, self::SKIP_DIRS, true)) {
                        continue;
                    }
                    $stack[] = [$full, $depth + 1];
                    continue;
                }
                if (!str_ends_with(strtolower($entry), '.md') || str_starts_with($entry, '.')) {
                    continue;
                }
                $relative = ltrim(str_replace('\\', '/', substr($full, strlen($root))), '/');
                $stat = @stat($full);
                $size = is_array($stat) ? (int) $stat['size'] : 0;
                $mtime = is_array($stat) ? (int) $stat['mtime'] : 0;
                $files[] = [
                    'path' => $relative,
                    'title' => $this->quickTitle($full),
                    'size' => $size,
                    'modified' => $mtime,
                    'depth' => $depth,
                ];
                if (count($files) >= $maxFiles) {
                    $this->logger->warning('scan: file cap reached', ['cap' => $maxFiles]);
                    break 2;
                }
            }
        }

        return $files;
    }

    /**
     * Cheap title for listings: frontmatter title, else first level-1 heading
     * found in the first 8 KB outside fenced code, else the filename.
     */
    public function quickTitle(string $absolutePath): string
    {
        $handle = @fopen($absolutePath, 'rb');
        if ($handle === false) {
            return basename($absolutePath, '.md');
        }
        $head = (string) fread($handle, 8192);
        fclose($handle);
        $head = str_replace(["\r\n", "\r"], "\n", $head);

        // Frontmatter title
        if (str_starts_with($head, "---\n")) {
            $end = strpos($head, "\n---", 4);
            if ($end !== false) {
                $block = substr($head, 4, $end - 4);
                if (preg_match('/^title\s*:\s*(.+)$/mi', $block, $m) === 1) {
                    $title = trim($m[1], " \t\"'");
                    if ($title !== '') {
                        return $title;
                    }
                }
            }
        }

        // First ATX H1 outside a fenced code block
        $inFence = false;
        foreach (explode("\n", $head) as $line) {
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line) === 1) {
                $inFence = !$inFence;
                continue;
            }
            if (!$inFence && preg_match('/^#\s+(.+?)\s*#*\s*$/', $line, $m) === 1) {
                $title = trim($m[1]);
                if ($title !== '') {
                    return $title;
                }
            }
        }

        return basename($absolutePath, '.md');
    }

    /**
     * Sort scanned files.
     *
     * @param array<int, array{path: string, title: string, size: int, modified: int, depth: int}> $files
     * @return array<int, array{path: string, title: string, size: int, modified: int, depth: int}>
     */
    public static function sortFiles(array $files, string $key, string $dir): array
    {
        $key = in_array($key, ['name', 'size', 'modified'], true) ? $key : 'modified';
        $mult = strtolower($dir) === 'asc' ? 1 : -1;
        usort($files, static function (array $a, array $b) use ($key, $mult): int {
            if ($key === 'name') {
                return $mult * strnatcasecmp($a['path'], $b['path']);
            }

            return $mult * ($a[$key] <=> $b[$key]) ?: strnatcasecmp($a['path'], $b['path']);
        });

        return $files;
    }

    /**
     * Neighbours of a document in the canonical (name-asc) order — used by the
     * prev/next pager so the order is stable regardless of the browser sort.
     *
     * @return array{prev: ?string, next: ?string} relative paths
     */
    public function neighbours(string $relative): array
    {
        $files = self::sortFiles($this->scan(), 'name', 'asc');
        $paths = array_column($files, 'path');
        $index = array_search($relative, $paths, true);
        if ($index === false) {
            return ['prev' => null, 'next' => null];
        }

        return [
            'prev' => $paths[$index - 1] ?? null,
            'next' => $paths[$index + 1] ?? null,
        ];
    }

    /** The configured home document, resolved — or null. */
    public function homeDocument(): ?string
    {
        $home = trim($this->config->str('HOME_DOC'));
        if ($home === '') {
            $home = 'index.md';
        }

        return $this->resolve($home);
    }

    /** Aggregate statistics for the admin dashboard. */
    public function stats(): array
    {
        $files = $this->scan();
        $totalSize = array_sum(array_column($files, 'size'));
        $dirs = [];
        foreach ($files as $file) {
            $dir = dirname($file['path']);
            if ($dir !== '.') {
                foreach (explode('/', $dir) as $i => $_) {
                    $dirs[implode('/', array_slice(explode('/', $dir), 0, $i + 1))] = true;
                }
            }
        }
        $newest = $files === [] ? null : max(array_column($files, 'modified'));

        return [
            'files' => count($files),
            'directories' => count($dirs),
            'bytes' => $totalSize,
            'newest_modified' => $newest,
            'browse_dir' => $this->browseDir(),
        ];
    }

    /** Every .md relative path (for sitemap/feed). @return list<string> */
    public function allPaths(): array
    {
        return array_column($this->scan(), 'path');
    }
}
