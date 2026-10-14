<?php

/**
 * MD.Viewer 3.0 — minimal cURL HTTP client used by the updater.
 *
 * Supports conditional GET (ETag / If-None-Match), hard timeouts, redirect
 * limits and a descriptive user agent. Never throws on transport failure —
 * callers get a structured result and decide.
 */

declare(strict_types=1);

namespace MdViewer\Core;

final class HttpClient
{
    public function __construct(
        private readonly int $timeout = 20,
        private readonly int $connectTimeout = 8,
        private readonly Logger $logger = new Logger(),
    ) {
    }

    /**
     * @return array{status: int, body: string, etag: ?string, error: ?string}
     */
    public function get(string $url, ?string $ifNoneMatch = null, array $headers = []): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'body' => '', 'etag' => null, 'error' => 'curl_init failed'];
        }

        $responseHeaders = [];
        $allHeaders = array_merge([
            'User-Agent: ' . Version::userAgent(),
            'Accept: */*',
        ], $headers);
        if ($ifNoneMatch !== null && $ifNoneMatch !== '') {
            $allHeaders[] = 'If-None-Match: ' . $ifNoneMatch;
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_HTTPHEADER => $allHeaders,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => function ($ch, string $header) use (&$responseHeaders): int {
                $len = strlen($header);
                $parts = explode(':', $header, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $len;
            },
        ]);

        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($body === false) {
            $this->logger->warning('HTTP GET failed', ['url' => $url, 'error' => $error]);

            return ['status' => $status, 'body' => '', 'etag' => null, 'error' => $error !== '' ? $error : 'transport error'];
        }

        return [
            'status' => $status,
            'body' => (string) $body,
            'etag' => $responseHeaders['etag'] ?? null,
            'error' => null,
        ];
    }

    /** @return array{status: int, data: mixed, error: ?string} */
    public function getJson(string $url, array $headers = []): array
    {
        $result = $this->get($url, null, $headers);
        if ($result['error'] !== null) {
            return ['status' => $result['status'], 'data' => null, 'error' => $result['error']];
        }
        if (!json_validate($result['body'])) {
            return ['status' => $result['status'], 'data' => null, 'error' => 'invalid JSON'];
        }

        return ['status' => $result['status'], 'data' => json_decode($result['body'], true), 'error' => null];
    }
}
