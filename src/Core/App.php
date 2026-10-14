<?php

/**
 * MD.Viewer 3.0 — application kernel.
 *
 * Boots configuration, logging, session, CSRF and the template engine,
 * installs the security headers (including the per-request CSP nonce) and the
 * fatal-error handlers, and acts as the lazy service locator for the rest of
 * the app. Two entries share it: the public viewer and the admin panel.
 */

declare(strict_types=1);

namespace MdViewer\Core;

use MdViewer\Files\FileManager;
use MdViewer\Files\MarkdownRepository;
use MdViewer\Files\SearchIndex;
use MdViewer\Files\Uploader;
use MdViewer\Markdown\MarkdownEngine;
use MdViewer\Theme\ThemeCatalog;
use MdViewer\View\Template;

final class App
{
    private static ?self $instance = null;

    public readonly Config $config;
    public readonly FeatureFlags $flags;
    public readonly Logger $logger;
    public readonly Session $session;
    public readonly Csrf $csrf;
    public readonly Request $request;
    public readonly AuditLog $audit;
    public readonly Template $tpl;

    private string $nonce;
    private string $entry;

    private ?MarkdownEngine $markdown = null;
    private ?MarkdownRepository $repo = null;
    private ?SearchIndex $search = null;
    private ?ThemeCatalog $themes = null;
    private ?Uploader $uploader = null;
    private ?FileManager $fileManager = null;

    private function __construct(string $entry, string $scriptUrl, string $assetBase)
    {
        $this->entry = $entry;
        $this->nonce = base64_encode(random_bytes(16));

        Paths::ensureStorage();

        $this->config = Config::instance();
        $this->logger = new Logger($this->config);
        $https = (string) ($_SERVER['HTTPS'] ?? 'off');
        $this->session = new Session(
            idleTimeout: 3600,
            secure: $https !== '' && $https !== 'off',
        );
        // Sessions back CSRF protection for the clipboard/upload APIs and the
        // admin login. First-party, HttpOnly, SameSite=Lax — no tracking.
        $this->session->start();
        $this->request = Request::fromGlobals($scriptUrl, $assetBase);
        $this->flags = new FeatureFlags($this->config, $this->request->cookies());
        $this->csrf = new Csrf($this->session);
        $this->audit = new AuditLog($this->request);
        $this->tpl = new Template();
        $this->tpl->setRequest($this->request);
        $this->tpl->setNonce($this->nonce);

        $this->installErrorHandlers();
        $this->sendSecurityHeaders();
    }

    public static function boot(string $entry, string $scriptUrl, string $assetBase): self
    {
        return self::$instance ??= new self($entry, $scriptUrl, $assetBase);
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            throw new \LogicException('App not booted');
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    public function entry(): string
    {
        return $this->entry;
    }

    public function nonce(): string
    {
        return $this->nonce;
    }

    public function isDevelopment(): bool
    {
        return $this->config->str('APP_ENV') === 'development' || $this->config->bool('APP_DEBUG');
    }

    // ── Lazy services ──────────────────────────────────────────────────────

    public function markdown(): MarkdownEngine
    {
        return $this->markdown ??= new MarkdownEngine($this->flags, $this->config, $this->logger);
    }

    public function repo(): MarkdownRepository
    {
        return $this->repo ??= new MarkdownRepository($this->config, $this->logger);
    }

    public function search(): SearchIndex
    {
        return $this->search ??= new SearchIndex($this->repo(), $this->logger, Paths::index());
    }

    public function themes(): ThemeCatalog
    {
        return $this->themes ??= new ThemeCatalog(Paths::config() . '/themes.php', Paths::customThemes());
    }

    public function uploader(): Uploader
    {
        return $this->uploader ??= new Uploader($this->config, $this->repo(), $this->logger, $this->audit);
    }

    public function fileManager(): FileManager
    {
        return $this->fileManager ??= new FileManager($this->config, $this->repo(), $this->logger, $this->audit);
    }

    // ── Security headers ───────────────────────────────────────────────────

    private function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        header('X-Powered-By: MD.Viewer ' . Version::VERSION);

        if ($this->config->bool('SECURITY_HEADERS')) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('Referrer-Policy: strict-origin-when-cross-origin');
            header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()");
            header('Cross-Origin-Opener-Policy: same-origin');
        }

        if ($this->config->bool('SECURITY_CSP')) {
            $nonce = $this->nonce;
            $imgExternal = $this->config->bool('ALLOW_EXTERNAL_IMAGES') ? ' https: data:' : '';
            $embedSrc = $this->config->bool('ALLOW_IFRAMES') ? ' https:' : '';
            // Mermaid injects inline styles into generated SVG, hence
            // 'unsafe-inline' for styles — documented trade-off, configurable.
            $csp = implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'",
                "style-src 'self' 'unsafe-inline'",
                "img-src 'self' blob: data:" . $imgExternal,
                "font-src 'self' data:",
                "connect-src 'self'",
                "media-src 'self'" . $embedSrc,
                "frame-src 'self'" . $embedSrc,
                "object-src 'none'",
                "frame-ancestors 'self'",
                "base-uri 'self'",
                "form-action 'self'",
            ]);
            header('Content-Security-Policy: ' . $csp);
        }
    }

    // ── Error handling ─────────────────────────────────────────────────────

    private function installErrorHandlers(): void
    {
        set_exception_handler(function (\Throwable $e): void {
            $this->renderThrowable($e);
        });

        set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
            if ((error_reporting() & $severity) === 0) {
                return false;
            }
            if ($this->isDevelopment()) {
                // Development: fail loudly so bugs surface immediately.
                throw new \ErrorException($message, 0, $severity, $file, $line);
            }
            // Production: log and continue — a notice must never take the page down.
            $this->logger->warning('PHP: ' . $message, ['file' => $file, 'line' => $line]);

            return true;
        });

        register_shutdown_function(function (): void {
            $error = error_get_last();
            if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
                $this->logger->error('Fatal: ' . $error['message'], ['file' => $error['file'], 'line' => $error['line']]);
                if (!headers_sent()) {
                    http_response_code(500);
                }
                echo $this->errorPage('Internal server error', 'A fatal error occurred. Details were written to the application log.');
            }
        });

        error_reporting(E_ALL);
        ini_set('display_errors', $this->isDevelopment() ? '1' : '0');
        ini_set('log_errors', '1');
    }

    private function renderThrowable(\Throwable $e): never
    {
        $this->logger->error('Unhandled ' . $e::class . ': ' . $e->getMessage(), [
            'file' => $e->getFile(),
            'line' => $e->getLine(),
            'trace' => $this->isDevelopment() ? explode("\n", $e->getTraceAsString()) : null,
        ]);

        $status = method_exists($e, 'statusCode') ? (int) $e->statusCode() : 500;
        if (!headers_sent()) {
            http_response_code($status === 0 ? 500 : $status);
        }

        if ($this->request->wantsJson() || $this->request->isAjax()) {
            echo (string) json_encode([
                'ok' => false,
                'error' => $this->isDevelopment() ? $e->getMessage() : 'internal error',
            ]);
            exit;
        }

        $details = $this->isDevelopment()
            ? $e::class . ': ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString()
            : null;

        echo $this->errorPage('Something went wrong', $details ?? 'The request could not be completed. The incident was logged.');
        exit;
    }

    private function errorPage(string $title, ?string $details): string
    {
        try {
            return $this->tpl->render('pages/error', [
                'app' => $this,
                'title' => $title,
                'details' => $details,
                'status' => http_response_code() ?: 500,
            ]);
        } catch (\Throwable) {
            // Template layer itself failed — fall back to a bare page.
            $safeTitle = htmlspecialchars($title, ENT_QUOTES);
            $safeDetails = $details !== null ? '<pre>' . htmlspecialchars($details, ENT_QUOTES) . '</pre>' : '';

            return "<!doctype html><meta charset=\"utf-8\"><meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">"
                . "<title>{$safeTitle} — MD.Viewer</title>"
                . "<body style=\"font-family:system-ui;max-width:40rem;margin:15vh auto;padding:0 1rem\">"
                . "<h1>{$safeTitle}</h1>{$safeDetails}</body>";
        }
    }
}
