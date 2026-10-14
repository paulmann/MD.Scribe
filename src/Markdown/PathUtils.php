<?php

/**
 * MD.Viewer 3.0 — relative path utilities (join + normalize, POSIX style).
 */

declare(strict_types=1);

namespace MdViewer\Markdown;

final class PathUtils
{
    /**
     * Join a document's directory with a relative reference and normalize
     * "." and ".." segments. Returns null when the result escapes the root.
     */
    public static function joinRelative(string $dir, string $relative): ?string
    {
        $dir = trim(str_replace('\\', '/', $dir), '/');
        $relative = str_replace('\\', '/', $relative);

        $segments = $dir === '' ? [] : explode('/', $dir);
        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                if ($segments === []) {
                    return null; // escapes the browse root
                }
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    /** Split "path#fragment" → [path, fragment|null] (fragment may be empty string). */
    public static function splitFragment(string $url): array
    {
        $pos = strpos($url, '#');
        if ($pos === false) {
            return [$url, null];
        }

        return [substr($url, 0, $pos), substr($url, $pos + 1)];
    }
}
