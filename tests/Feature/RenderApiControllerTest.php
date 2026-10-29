<?php

declare(strict_types=1);

namespace MdViewer\Tests\Feature;

use MdViewer\Api\RenderService;
use MdViewer\Core\Request;
use MdViewer\Core\Response;
use MdViewer\Core\Version;
use MdViewer\Http\Controllers\RenderApiController;
use MdViewer\Tests\TestCase;

/**
 * Machine render endpoint: key authentication and the documented status codes.
 * The controller is exercised through a real Request, so the header/query key
 * lookups, the JSON/form body fallback and the response envelope are covered.
 */
final class RenderApiControllerTest extends TestCase
{
    private string $apiKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiKey = 'mdv_' . bin2hex(random_bytes(20));
        $this->config(['API_KEY' => $this->apiKey]);
    }

    public function testValidHeaderKeyRendersMarkdown(): void
    {
        $response = $this->handle(
            ['api' => 'render'],
            ['content' => "# Заголовок\n\n- раз\n- два"],
            ['HTTP_X_API_KEY' => $this->apiKey],
        );

        self::assertSame(200, $response->status());
        $body = $this->decode($response);
        self::assertTrue($body['ok']);
        self::assertSame('markdown', $body['data']['format']);
        self::assertStringContainsString('<h1', $body['data']['html']);
        self::assertMatchesRegularExpression('/<li>(?:<p>)?раз/u', $body['data']['html']);
        self::assertSame(strlen($body['data']['html']), $body['data']['bytes']);
        self::assertSame('MD.Scribe/' . Version::VERSION, $body['data']['renderer']);
        self::assertIsNumeric($body['data']['elapsed_ms']);
    }

    public function testValidQueryKeyRendersHtmlAndStripsScript(): void
    {
        $response = $this->handle(
            ['api' => 'render', 'api_key' => $this->apiKey],
            ['content' => '<p>ok</p><script>alert(1)</script>', 'format' => 'html'],
        );

        self::assertSame(200, $response->status());
        $body = $this->decode($response);
        self::assertSame('html', $body['data']['format']);
        self::assertStringContainsString('<p>ok</p>', $body['data']['html']);
        self::assertStringNotContainsString('<script', $body['data']['html']);
    }

    public function testTextFormatIsWrappedInWrappingBlock(): void
    {
        $response = $this->handle(
            ['api' => 'render'],
            ['content' => 'просто текст <b>не тег</b>', 'format' => 'text'],
            ['HTTP_X_API_KEY' => $this->apiKey],
        );

        $body = $this->decode($response);
        self::assertSame(200, $response->status());
        self::assertSame('text', $body['data']['format']);
        self::assertStringStartsWith('<div class="md-text"', $body['data']['html']);
        self::assertStringContainsString('white-space:pre-wrap', $body['data']['html']);
        self::assertStringContainsString('&lt;b&gt;', $body['data']['html']);
    }

    public function testWrongKeyIsRejected(): void
    {
        $response = $this->handle(
            ['api' => 'render'],
            ['content' => 'x'],
            ['HTTP_X_API_KEY' => 'mdv_' . str_repeat('0', 40)],
        );

        self::assertSame(401, $response->status());
        $body = $this->decode($response);
        self::assertFalse($body['ok']);
        self::assertSame('bad_key', $body['error']['code']);
    }

    public function testMissingKeyIsRejectedWhenTheControllerIsReachedDirectly(): void
    {
        // The front controller keeps the legacy session+CSRF branch when no key
        // is supplied at all; the controller itself never accepts an anonymous
        // call either (second line of defence).
        $response = $this->handle(['api' => 'render'], ['content' => 'x']);

        self::assertSame(401, $response->status());
        self::assertSame('bad_key', $this->decode($response)['error']['code']);
    }

    public function testEmptyKeyIsRejectedEvenWhenItMatchesAnUnsetConfiguration(): void
    {
        $this->config(['API_KEY' => '']);
        $response = $this->handle(['api' => 'render', 'api_key' => ''], ['content' => 'x'], ['HTTP_X_API_KEY' => '']);

        self::assertSame(401, $response->status());
        self::assertSame('bad_key', $this->decode($response)['error']['code']);
    }

    public function testEmptyContentIsRejected(): void
    {
        $response = $this->handle(
            ['api' => 'render', 'api_key' => $this->apiKey],
            ['content' => ''],
        );

        self::assertSame(422, $response->status());
        self::assertSame('empty_content', $this->decode($response)['error']['code']);
    }

    public function testUnknownFormatIsRejected(): void
    {
        $response = $this->handle(
            ['api' => 'render', 'api_key' => $this->apiKey],
            ['content' => 'x', 'format' => 'pdf'],
        );

        self::assertSame(422, $response->status());
        self::assertSame('bad_format', $this->decode($response)['error']['code']);
    }

    public function testNonStringFormatIsRejected(): void
    {
        $response = $this->handle(
            ['api' => 'render', 'api_key' => $this->apiKey],
            ['content' => 'x', 'format' => ['markdown']],
        );

        self::assertSame(422, $response->status());
        self::assertSame('bad_format', $this->decode($response)['error']['code']);
    }

    public function testOversizedContentIsRejected(): void
    {
        $response = $this->handle(
            ['api' => 'render', 'api_key' => $this->apiKey],
            ['content' => str_repeat('a', RenderService::MAX_CONTENT_BYTES + 1)],
        );

        self::assertSame(413, $response->status());
        self::assertSame('too_large', $this->decode($response)['error']['code']);
    }

    /**
     * @param array<string, mixed>  $query
     * @param array<string, mixed>  $post
     * @param array<string, string> $server extra $_SERVER entries (headers)
     */
    private function handle(array $query, array $post, array $server = []): Response
    {
        $request = new Request('/index.php', '', $query, $post, [], [], $server + [
            'REQUEST_METHOD' => 'POST',
        ]);

        return (new RenderApiController($this->config()))->handle($request);
    }

    /** @return array{ok: bool, data?: array<string, mixed>, error?: array<string, string>} */
    private function decode(Response $response): array
    {
        $decoded = json_decode($response->body(), true);
        self::assertIsArray($decoded, 'the API must answer JSON');

        return $decoded;
    }
}
