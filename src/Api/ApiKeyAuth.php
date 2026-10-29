<?php

/**
 * MD.Scribe 3.2 — machine API credential check.
 *
 * One rule, one place: the `API_KEY` from `.md.ini` (format `mdv_` + 40 hex
 * characters, generated on first load) compared with `hash_equals()` so a
 * wrong key cannot be guessed byte by byte. An unset key never authenticates.
 */

declare(strict_types=1);

namespace MdViewer\Api;

final class ApiKeyAuth
{
    public static function isValid(string $expectedKey, ?string $providedKey): bool
    {
        if ($expectedKey === '' || $providedKey === null || $providedKey === '') {
            return false;
        }

        return hash_equals($expectedKey, $providedKey);
    }
}
