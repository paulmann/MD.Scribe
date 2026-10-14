<?php

/**
 * MD.Viewer 3.0 — HTTP response value object.
 */

declare(strict_types=1);

namespace MdViewer\Core;

final class Response
{
    /** @var array<string, string> */
    private array $headers = [];

    /** @param array<string, string> $headers */
    public function __construct(
        private string $body = '',
        private int $status = 200,
        array $headers = [],
    ) {
        foreach ($headers as $name => $value) {
            $this->headers[(string) $name] = (string) $value;
        }
        if (!isset($this->headers['Content-Type'])) {
            $this->headers['Content-Type'] = 'text/html; charset=utf-8';
        }
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(
            (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE),
            $status,
            [
                'Content-Type' => 'application/json; charset=utf-8',
                'Cache-Control' => 'no-store',
                'X-Content-Type-Options' => 'nosniff',
            ],
        );
    }

    public static function text(string $text, int $status = 200): self
    {
        return new self($text, $status, ['Content-Type' => 'text/plain; charset=utf-8']);
    }

    public static function xml(string $xml, int $status = 200): self
    {
        return new self($xml, $status, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    public static function redirect(string $to, int $status = 302): self
    {
        return new self('', $status, ['Location' => $to, 'Cache-Control' => 'no-store']);
    }

    public static function notFound(string $html = ''): self
    {
        return self::html($html, 404);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string, string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function withHeader(string $name, string $value): self
    {
        $clone = clone $this;
        $clone->headers[$name] = $value;

        return $clone;
    }

    public function withStatus(int $status): self
    {
        $clone = clone $this;
        $clone->status = $status;

        return $clone;
    }

    public function withCache(int $maxAge, bool $public = true): self
    {
        return $this->withHeader(
            'Cache-Control',
            ($public ? 'public' : 'private') . ', max-age=' . $maxAge,
        );
    }

    public function noStore(): self
    {
        return $this->withHeader('Cache-Control', 'no-store');
    }

    /**
     * Serve a file as an opaque download (the v2 rule stands: a .md is text,
     * but announced as an attachment under an author-chosen filename it could
     * become a script in the browser's eyes — so it is octet-stream + nosniff).
     */
    public static function download(string $absolutePath, ?string $downloadName = null): self
    {
        $name = $downloadName ?? basename($absolutePath);
        $ascii = str_replace(['"', '\\'], '_', (string) preg_replace('/[^\x20-\x7E]/', '_', $name));
        if (trim($ascii, '_') === '') {
            $ascii = 'document.md';
        }
        $size = @filesize($absolutePath);
        $headers = [
            'Content-Type' => 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'attachment; filename="' . $ascii . '"; filename*=UTF-8\'\'' . rawurlencode($name),
            'Cache-Control' => 'no-store',
        ];
        if (is_int($size)) {
            $headers['Content-Length'] = (string) $size;
        }

        return new self((string) @file_get_contents($absolutePath), 200, $headers);
    }

    public function send(): never
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value, true);
            }
        }
        echo $this->body;
        exit;
    }
}
