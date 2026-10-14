<?php

/**
 * MD.Viewer 3.0 — multi-layer path validation.
 *
 * A faithful, tested port of the v2.18 validator (all fourteen layers), with
 * the depth limit made configurable. One rule, one place: the viewer, the
 * downloader, the uploader, the per-directory ini chain and the admin file
 * manager all refuse paths through this class and through nothing else.
 *
 * Layer summary (see validate()):
 *   1  length limit            8  depth limit
 *   2  NUL byte                9  .md extension (case-insensitive)
 *   3  control characters     10  base directory resolves
 *   4  url-decode + recheck   11  target resolves (exists)
 *   5  absolute paths         12  realpath containment inside base
 *   6  traversal sequences    13  regular file, not device/dir
 *   7  character whitelist    14  extension re-checked post-symlink
 *
 * The v2.16/v2.18 whitelist stands: [A-Za-z0-9._-/ ], space, Unicode letters
 * \p{L}, combining marks \p{M} and dash punctuation \p{Pd} (em dashes belong
 * in filenames; % ? * : " \ do not — they are \p{Po}, not \p{Pd}).
 */

declare(strict_types=1);

namespace MdViewer\Files;

final class PathValidator
{
    public const int MAX_PARAM_LENGTH = 255;

    /** Media types the viewer is willing to serve from the content root. */
    public const array MEDIA_EXTENSIONS = [
        'png', 'jpg', 'jpeg', 'gif', 'webp', 'avif', 'bmp', 'ico', 'svg',
        'pdf', 'txt', 'csv', 'json', 'xml', 'zip', 'mp4', 'webm', 'mp3', 'ogg', 'wav',
    ];

    /** The one whitelist. Kept byte-identical for files and directories. */
    private const string WHITELIST = '#^[A-Za-z0-9._\-/ \p{L}\p{M}\p{Pd}]+$#u';

    public function __construct(private readonly int $maxDepth = 8)
    {
    }

    /**
     * Validate a user-supplied relative .md path against a base directory.
     * Returns the absolute realpath on success, null on any violation.
     */
    public function validateFile(string $file, string $baseDir): ?string
    {
        $normalized = $this->normalizeRelative($file);
        if ($normalized === null) {
            return null;
        }

        // Layer 9: must be a .md file (case-insensitive).
        if (!str_ends_with(mb_strtolower($normalized, 'UTF-8'), '.md')) {
            return null;
        }

        // Layer 10: base directory must resolve.
        $realBase = realpath($baseDir);
        if ($realBase === false || !is_dir($realBase)) {
            return null;
        }

        // Layer 11: target must exist.
        $realPath = realpath($realBase . DIRECTORY_SEPARATOR . $normalized);
        if ($realPath === false) {
            return null;
        }

        // Layer 12: containment — realpath MUST stay inside the base directory.
        if (!$this->insideRoot($realPath, $realBase)) {
            return null;
        }

        // Layer 13: regular file only.
        if (!is_file($realPath)) {
            return null;
        }

        // Layer 14: extension re-check after symlink resolution.
        if (mb_strtolower(pathinfo($realPath, PATHINFO_EXTENSION), 'UTF-8') !== 'md') {
            return null;
        }

        return $realPath;
    }

    /**
     * Split a client-supplied relative directory into validated segments.
     * [] means the browse root itself; null means "refuse".
     *
     * @return list<string>|null
     */
    public function dirSegments(string $dir): ?array
    {
        // NUL/control check on the RAW input — trim() would strip a trailing
        // NUL and hide the injection attempt.
        if (str_contains($dir, "\0") || preg_match('/[\x00-\x1F\x7F]/', $dir) === 1) {
            return null;
        }
        if (trim($dir) === '') {
            return [];
        }
        $normalized = $this->normalizeRelative($dir);
        if ($normalized === null) {
            return null;
        }
        $normalized = trim($normalized, '/');
        if ($normalized === '' || $normalized === '.') {
            return [];
        }

        $segments = [];
        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            $segments[] = $segment;
        }

        return $segments;
    }

    /**
     * Validate a relative directory inside the base and resolve it.
     * Returns the absolute realpath of the directory, or null.
     */
    public function validateDir(string $dir, string $baseDir): ?string
    {
        $segments = $this->dirSegments($dir);
        if ($segments === null) {
            return null;
        }
        $realBase = realpath($baseDir);
        if ($realBase === false || !is_dir($realBase)) {
            return null;
        }
        $target = $realBase . ($segments === [] ? '' : DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $segments));
        $real = realpath($target);
        if ($real === false || !is_dir($real) || !$this->insideRoot($real, $realBase)) {
            return null;
        }

        return $real;
    }

    /**
     * Validate a relative *new* path (target may not exist yet — used by the
     * admin file manager and uploads). The parent directory must exist and
     * resolve inside the base; the final name is whitelist-checked.
     */
    public function validateNewPath(string $relative, string $baseDir, bool $allowDirs = false): ?string
    {
        $normalized = $this->normalizeRelative($relative);
        if ($normalized === null) {
            return null;
        }
        $normalized = trim($normalized, '/');
        if ($normalized === '') {
            return null;
        }
        if (!$allowDirs && !str_ends_with(mb_strtolower($normalized, 'UTF-8'), '.md')) {
            return null;
        }

        $realBase = realpath($baseDir);
        if ($realBase === false || !is_dir($realBase)) {
            return null;
        }

        $parent = dirname($normalized);
        $parentReal = $parent === '.'
            ? $realBase
            : realpath($realBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $parent));
        if ($parentReal === false || !is_dir($parentReal) || !$this->insideRoot($parentReal, $realBase)) {
            return null;
        }

        $target = $parentReal . DIRECTORY_SEPARATOR . basename(str_replace('/', DIRECTORY_SEPARATOR, $normalized));

        return $target;
    }

    /**
     * Layers 1–8: everything that can be decided on the string alone.
     * Returns the normalized relative path or null.
     */
    private function normalizeRelative(string $input): ?string
    {
        // Layers 2–3 on the RAW input first — trim() strips trailing NULs and
        // would hide the injection attempt.
        if (str_contains($input, "\0")) {
            return null;
        }
        if (preg_match('/[\x00-\x1F\x7F]/', $input) === 1) {
            return null;
        }

        $input = trim($input);

        // Layer 1: length.
        if ($input === '' || strlen($input) > self::MAX_PARAM_LENGTH) {
            return null;
        }
        // Layer 4: URL-decode, then re-check for control characters.
        $decoded = urldecode($input);
        if (preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1) {
            return null;
        }
        // Layer 5: absolute paths (Unix, Windows drive, UNC).
        if (str_starts_with($decoded, '/') || str_starts_with($decoded, '\\')
            || preg_match('/^[A-Za-z]:/', $decoded) === 1) {
            return null;
        }
        // Layer 6: normalize separators, reject traversal.
        $normalized = str_replace(['\\', '//'], '/', $decoded);
        if (preg_match('#(?:^|/)\.\.(?:/|$)|\.\.$#', $normalized) === 1) {
            return null;
        }
        // Layer 7: strict character whitelist (v2.16 + v2.18 rules).
        if (preg_match(self::WHITELIST, $normalized) !== 1) {
            return null;
        }
        // Layer 8: depth limit.
        if (substr_count($normalized, '/') > $this->maxDepth) {
            return null;
        }

        return $normalized;
    }

    /** True when $path is $root itself or lives below it (both realpath'd). */
    public function insideRoot(string $path, string $root): bool
    {
        if ($path === $root) {
            return true;
        }

        return str_starts_with($path, rtrim($root, '/\\') . DIRECTORY_SEPARATOR);
    }

    /**
     * Validate a user-supplied relative path to a *media* asset (images and
     * a small set of document formats) inside the base directory. Same layers
     * as validateFile(); only the extension whitelist differs.
     */
    public function validateMedia(string $file, string $baseDir): ?string
    {
        $normalized = $this->normalizeRelative($file);
        if ($normalized === null) {
            return null;
        }
        $ext = strtolower(pathinfo($normalized, PATHINFO_EXTENSION));
        if (!in_array($ext, self::MEDIA_EXTENSIONS, true)) {
            return null;
        }
        $realBase = realpath($baseDir);
        if ($realBase === false || !is_dir($realBase)) {
            return null;
        }
        $realPath = realpath($realBase . DIRECTORY_SEPARATOR . $normalized);
        if ($realPath === false || !$this->insideRoot($realPath, $realBase) || !is_file($realPath)) {
            return null;
        }

        return $realPath;
    }

    /** Absolute → relative (with '/' separators), or null when outside root. */
    public function relativize(string $absolute, string $root): ?string
    {
        $realRoot = realpath($root);
        $real = realpath($absolute);
        if ($realRoot === false || $real === false || !$this->insideRoot($real, $realRoot)) {
            return null;
        }
        $relative = ltrim(substr($real, strlen($realRoot)), '/\\');

        return str_replace(DIRECTORY_SEPARATOR, '/', $relative);
    }
}
