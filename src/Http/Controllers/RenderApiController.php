<?php

/**
 * MD.Scribe 3.2 — machine render API controller.
 *
 * `POST /index.php?api=render` with the `API_KEY` from `.md.ini` in the
 * `X-Api-Key` header (or `?api_key=`). Body is JSON:
 *
 *     {"content": "…", "format": "auto"}          // auto|markdown|html|text
 *
 * Success:    `{ok: true, data: {format, html, bytes, elapsed_ms, renderer}}`
 * Failure:    `{ok: false, error: {code, message}}` with a matching status —
 *             `401 bad_key`, `422 empty_content`, `422 bad_format`,
 *             `413 too_large`.
 *
 * The endpoint is stateless: no session, no CSRF token, nothing written to disk.
 * The legacy session+CSRF `POST ?api=render` (clipboard preview) is untouched —
 * the front controller only routes here when a key is actually supplied.
 */

declare(strict_types=1);

namespace MdViewer\Http\Controllers;

use MdViewer\Api\ApiKeyAuth;
use MdViewer\Api\RenderError;
use MdViewer\Api\RenderService;
use MdViewer\Core\Config;
use MdViewer\Core\Request;
use MdViewer\Core\Response;
use MdViewer\Core\Version;

final class RenderApiController
{
    public function __construct(
        private readonly Config $config,
        private readonly ?RenderService $service = null,
    ) {
    }

    public function handle(Request $request): Response
    {
        $key = $request->header('X-Api-Key');
        if ($key === null || $key === '') {
            $key = $request->query('api_key');
        }

        // JSON is the contract; form-encoded fields are accepted for curl users
        // who forget the header ([[ -d ]]), never mixed with the legacy branch.
        $payload = $request->jsonBody();
        if ($payload === []) {
            $payload = $request->postAll();
        }

        return $this->serve($key, $payload);
    }

    /**
     * @param array<string, mixed> $payload decoded body: `content`, `format`
     */
    public function serve(?string $providedKey, array $payload): Response
    {
        if (!ApiKeyAuth::isValid($this->config->str('API_KEY'), $providedKey)) {
            return self::failure('bad_key', 401, 'invalid or missing API key');
        }

        $started = microtime(true);
        $content = is_string($payload['content'] ?? null) ? $payload['content'] : '';
        $format = $payload['format'] ?? 'auto';

        try {
            $fragment = $this->renderService()->process($content, is_string($format) ? $format : '');
        } catch (RenderError $error) {
            return self::failure($error->errorCode(), $error->statusCode(), $error->getMessage());
        }

        return Response::json([
            'ok' => true,
            'data' => [
                'format' => $fragment->format,
                'html' => $fragment->html,
                'bytes' => strlen($fragment->html),
                'elapsed_ms' => round((microtime(true) - $started) * 1000, 2),
                'renderer' => 'MD.Scribe/' . Version::VERSION,
            ],
        ]);
    }

    private static function failure(string $code, int $status, string $message): Response
    {
        return Response::json([
            'ok' => false,
            'error' => ['code' => $code, 'message' => $message],
        ], $status);
    }

    private function renderService(): RenderService
    {
        return $this->service ?? new RenderService();
    }
}
