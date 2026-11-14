<?php

/**
 * MD.Scribe — fingerprint of a served page.
 *
 *   php page-fingerprint.php https://example.org/mdscribe/
 *
 * Prints one line: `<sha256> <bytes> <url>`. Two runs a minute apart produce the
 * same value, because the only per-request parts of the HTML — the CSP nonce and
 * the CSRF token — are normalised before hashing. Everything else is content: the
 * version, the rendered markup, the navigation, the diagram placeholders.
 *
 * Why: a deploy report that says "HTTP 200" proves a request was answered, not
 * that the answer is the release. This value can be quoted next to a release tag
 * and re-measured later; a change means the page changed.
 *
 * Exit 1 on a transport failure or a non-200 answer.
 */

declare(strict_types=1);

$url = (string) ($argv[1] ?? '');
if ($url === '' || preg_match('#^https?://#i', $url) !== 1) {
    fwrite(STDERR, "usage: php page-fingerprint.php https://host/path/\n");
    exit(2);
}

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 30,
        'ignore_errors' => true,
        'follow_location' => 1,
        'max_redirects' => 3,
        'header' => "User-Agent: MD.Scribe-page-fingerprint\r\nCache-Control: no-cache\r\n",
    ],
]);

$body = @file_get_contents($url, false, $context);
if ($body === false) {
    $error = error_get_last();
    fwrite(STDERR, 'cannot read ' . $url . ': ' . ($error['message'] ?? 'unknown error') . "\n");
    exit(1);
}

$status = 0;
foreach ($http_response_header ?? [] as $header) {
    if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m) === 1) {
        $status = (int) $m[1];
    }
}
if ($status !== 200) {
    fwrite(STDERR, $url . ' answered HTTP ' . $status . "\n");
    exit(1);
}

// Per-request values, replaced by a fixed token. Anything else that differs
// between two renders is content and belongs in the hash.
$normalised = preg_replace([
    '/nonce="[^"]*"/',
    '/"(?:csrf|csrf_token|token)":"[^"]*"/',
], [
    'nonce="…"',
    '"token":"…"',
], $body);

if (!is_string($normalised)) {
    fwrite(STDERR, "normalisation failed\n");
    exit(1);
}

printf("%s %d %s\n", hash('sha256', $normalised), strlen($normalised), $url);
exit(0);
