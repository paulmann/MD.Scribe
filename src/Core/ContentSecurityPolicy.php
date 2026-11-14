<?php

declare(strict_types=1);

namespace MdViewer\Core;

/**
 * The Content-Security-Policy header, built from directives rather than from
 * concatenated strings.
 *
 * The policy used to be assembled inline in App::sendSecurityHeaders(), where the
 * base of `img-src` already ended in `data:` and the external-image switch appended
 * `... https: data:` — so a host with ALLOW_EXTERNAL_IMAGES on served
 * `img-src 'self' blob: data: https: data:` (measured on production on 2026-11-14).
 * A browser ignores the repeat, so nothing broke; but a policy that is written by
 * string concatenation is a policy nobody can check, and the next switch would add
 * another duplicate.
 *
 * Sources are therefore collected per directive and de-duplicated once, keeping the
 * order in which they were declared.
 */
final class ContentSecurityPolicy
{
    /**
     * @param bool $externalImages allow `https:` images (ALLOW_EXTERNAL_IMAGES)
     * @param bool $embeds         allow `https:` frames and media (ALLOW_IFRAMES)
     * @return array<string, list<string>> directive => sources, in declared order
     */
    public static function directives(string $nonce, bool $externalImages, bool $embeds): array
    {
        $policy = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-" . $nonce . "'"],
            // Mermaid injects inline styles into generated SVG: documented trade-off.
            'style-src' => ["'self'", "'unsafe-inline'"],
            'img-src' => ["'self'", 'blob:', 'data:'],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'"],
            'media-src' => ["'self'"],
            'frame-src' => ["'self'"],
            'object-src' => ["'none'"],
            'frame-ancestors' => ["'self'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
        ];

        if ($externalImages) {
            $policy['img-src'][] = 'https:';
        }
        if ($embeds) {
            $policy['media-src'][] = 'https:';
            $policy['frame-src'][] = 'https:';
        }

        foreach ($policy as $directive => $sources) {
            $policy[$directive] = array_values(array_unique($sources));
        }

        return $policy;
    }

    /** The header value: `directive sources; directive sources; …` */
    public static function header(string $nonce, bool $externalImages, bool $embeds): string
    {
        $parts = [];
        foreach (self::directives($nonce, $externalImages, $embeds) as $directive => $sources) {
            $parts[] = $directive . ' ' . implode(' ', $sources);
        }

        return implode('; ', $parts);
    }
}
