<?php

/**
 * MD.Scribe 3.x — strip badge plates from README-style Markdown (in memory).
 *
 * README files often start with a row of shields/badges — images served by
 * img.shields.io, badgen.net, codecov, CI providers, etc. Those images are
 * meaningless outside GitHub (and often blocked by CSP/referrer rules), so an
 * embedder may want to drop the plate before rendering.
 *
 * The source string is never modified on disk: this class returns a cleaned
 * copy. Only lines that contain nothing but badge markup/links are removed;
 * prose that happens to mention a badge URL is kept.
 */

declare(strict_types=1);

namespace MdViewer\Standalone;

final class BadgeStripper
{
    /** Image hosts used by README badge services. */
    private const array BADGE_PATTERNS = [
        '~img\.shields\.io~i',
        '~badgen\.net~i',
        '~badge\.fury\.io~i',
        '~codecov\.io~i',
        '~coveralls\.io~i',
        '~(?:app\.)?travis-ci\.(?:org|com)~i',
        '~github\.com/[^\s)]+/(?:actions/)?workflows?/[^\s)]+/badge~i',
        '~gitlab\.com/[^\s)]+/badges/~i',
        '~api\.codacy\.com~i',
        '~sonarcloud\.io~i',
        '~snyk\.io~i',
        '~pepy\.tech~i',
        '~badgesize\.io~i',
        '~badge\.buildkite\.com~i',
        '~ci\.appveyor\.com~i',
        '~img\.badge~i',
    ];

    /**
     * Return $markdown without badge-only lines / empty wrapper paragraphs.
     * The input string is untouched.
     */
    public static function strip(string $markdown): string
    {
        $lines = preg_split('/\R/u', $markdown);
        if ($lines === false) {
            return $markdown;
        }

        $kept = [];
        foreach ($lines as $line) {
            if (self::isBadgeLine($line)) {
                continue;
            }
            $kept[] = $line;
        }

        $text = implode("\n", $kept);

        // HTML wrappers left behind by a badge row (<p align="center"> … </p>).
        $text = (string) preg_replace(
            '~<(p|div)\b[^>]*(?:align\s*=\s*["\']?center["\']?)?[^>]*>\s*\n?\s*</\1>~iu',
            '',
            $text,
        );

        // Collapse the blank lines left where the plate used to be.
        $text = (string) preg_replace("/\n{3,}/u", "\n\n", $text);

        return rtrim($text) . "\n";
    }

    private static function isBadgeLine(string $line): bool
    {
        $trimmed = trim($line);
        if ($trimmed === '') {
            return false;
        }

        $hasBadge = false;
        foreach (self::BADGE_PATTERNS as $pattern) {
            if (preg_match($pattern, $trimmed) === 1) {
                $hasBadge = true;
                break;
            }
        }
        if (!$hasBadge) {
            return false;
        }

        // Strip Markdown images, links, HTML tags and bare URLs. If nothing
        // readable remains, the line is a pure badge line.
        $rest = (string) preg_replace('~!\[[^\]]*\]\([^)]*\)~u', '', $trimmed);
        $rest = (string) preg_replace('~\[[^\]]*\]\([^)]*\)~u', '', $rest);
        $rest = (string) preg_replace('~<[^>]+>~u', '', $rest);
        $rest = (string) preg_replace('~https?://\S+~u', '', $rest);
        $rest = (string) preg_replace('~[\s\x{00A0}|·•,;:/\-–—]+~u', '', $rest);

        return $rest === '';
    }
}
