<?php

/**
 * MD.Scribe 3.2 — machine render API failure (invalid input).
 *
 * Carries the HTTP status and the stable machine-readable `code` the API
 * envelope reports, so the controller stays a thin translator:
 *
 *     {ok: false, error: {code: "empty_content", message: "…"}}
 */

declare(strict_types=1);

namespace MdViewer\Api;

final class RenderError extends \RuntimeException
{
    public function __construct(
        private readonly string $errorCode,
        private readonly int $statusCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function emptyContent(): self
    {
        return new self('empty_content', 422, 'content is empty');
    }

    public static function badFormat(string $format): self
    {
        return new self('bad_format', 422, 'unknown format: ' . $format . ' (auto|markdown|html|text)');
    }

    public static function tooLarge(int $bytes): self
    {
        return new self(
            'too_large',
            413,
            'content is ' . $bytes . ' bytes; the limit is ' . RenderService::MAX_CONTENT_BYTES,
        );
    }

    /** Stable machine-readable code, e.g. `bad_key`. */
    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }
}
