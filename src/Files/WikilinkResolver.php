<?php

/**
 * MD.Scribe 3.3 — wikilink target rules.
 *
 * One place for the syntax and the resolution order, shared by the renderer,
 * the backlink index and the tests:
 *
 *   [[Page]]            → docs/Page.md when the source lives in docs/, else Page.md
 *   [[Page|Alias]]      → same target, different label
 *   [[Page#Heading]]    → same target, anchor slugified like a heading id
 *   [[#Heading]]        → anchor inside the current document
 *
 * Targets are normalised before use and never allowed to leave the browse
 * root: absolute paths and `..` segments are refused, and anything that
 * survives is still re-validated by PathValidator when it is opened.
 */

declare(strict_types=1);

namespace MdViewer\Files;

final class WikilinkResolver
{
    /** Hard cap on one target — keeps a pathological line bounded. */
    public const int MAX_TARGET_BYTES = 512;

    public function __construct(private readonly MarkdownRepository $repository)
    {
    }

    /**
     * Split the inside of `[[…]]` into its three parts.
     *
     * @return array{target: string, label: ?string, anchor: ?string}|null null when the link is empty
     */
    public static function split(string $inner): ?array
    {
        $inner = trim($inner);
        if ($inner === '' || strlen($inner) > self::MAX_TARGET_BYTES * 2) {
            return null;
        }

        $label = null;
        if (str_contains($inner, '|')) {
            [$inner, $label] = array_map('trim', explode('|', $inner, 2));
            $label = $label === '' ? null : $label;
        }

        $anchor = null;
        if (str_contains($inner, '#')) {
            [$inner, $anchor] = array_map('trim', explode('#', $inner, 2));
            $anchor = $anchor === '' ? null : $anchor;
        }

        $inner = trim($inner);
        if ($inner === '' && $anchor === null) {
            return null;
        }

        return ['target' => $inner, 'label' => $label, 'anchor' => $anchor];
    }

    /**
     * Normalise a raw target into a relative `.md` path.
     *
     * Returns null when the target is empty, too long, or shaped like a path
     * that could escape the browse root (absolute, `..` segment, scheme).
     */
    public static function normalize(string $target): ?string
    {
        $target = trim($target);
        if ($target === '' || strlen($target) > self::MAX_TARGET_BYTES) {
            return null;
        }

        $target = str_replace('\\', '/', $target);
        if (str_contains($target, "\0") || str_contains($target, '://')) {
            return null;
        }

        while (str_starts_with($target, './')) {
            $target = substr($target, 2);
        }
        $target = ltrim($target, '/');
        $collapsed = preg_replace('#/{2,}#', '/', $target);
        $target = is_string($collapsed) ? $collapsed : $target;

        foreach (explode('/', $target) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        if (!str_ends_with(strtolower($target), '.md')) {
            $target .= '.md';
        }

        return $target;
    }

    /**
     * Resolution order: next to the source document first, then the root.
     *
     * @return list<string> relative candidate paths
     */
    public static function candidates(string $target, string $docDir): array
    {
        $normalized = self::normalize($target);
        if ($normalized === null) {
            return [];
        }

        $dir = trim(str_replace('\\', '/', $docDir), '/');
        $candidates = [];
        if ($dir !== '' && $dir !== '.') {
            $candidates[] = $dir . '/' . $normalized;
        }
        $candidates[] = $normalized;

        return array_values(array_unique($candidates));
    }

    /** Relative path of the document this link points at, or null when absent. */
    public function resolve(string $target, string $docDir): ?string
    {
        foreach (self::candidates($target, $docDir) as $candidate) {
            if ($this->repository->resolve($candidate) !== null) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Wikilinks written in a document source, for the backlink index.
     *
     * CommonMark never parses inline syntax inside code spans or fenced
     * blocks, so both are removed first; otherwise a `[[example]]` in a code
     * sample would create a phantom backlink.
     *
     * @return list<array{target: string, anchor: ?string}>
     */
    public static function extract(string $markdown): array
    {
        $source = self::stripCode($markdown);
        if (preg_match_all('/\[\[([^\[\]\n]{1,512})\]\]/u', $source, $matches) === false) {
            return [];
        }

        $links = [];
        foreach ($matches[1] as $inner) {
            $parts = self::split((string) $inner);
            if ($parts === null || $parts['target'] === '') {
                continue;
            }
            $links[] = ['target' => $parts['target'], 'anchor' => $parts['anchor']];
        }

        return $links;
    }

    /** Remove fenced blocks and inline code spans from a Markdown source. */
    private static function stripCode(string $markdown): string
    {
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $markdown));
        $kept = [];
        $fence = null;

        foreach ($lines as $line) {
            if ($fence !== null) {
                if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line, $m) === 1
                    && $m[1][0] === $fence[0]
                    && strlen($m[1]) >= strlen($fence)
                ) {
                    $fence = null;
                }
                continue;
            }

            if (preg_match('/^ {0,3}(`{3,}|~{3,})/', $line, $m) === 1) {
                $fence = $m[1];
                continue;
            }

            $kept[] = $line;
        }

        return (string) preg_replace('/`+[^`\n]*`+/', '', implode("\n", $kept));
    }
}
