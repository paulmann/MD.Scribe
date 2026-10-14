<?php

/**
 * MD.Viewer 3.0 — template engine.
 *
 * Plain PHP templates with layout inheritance:
 *
 *   // templates/pages/themes.php
 *   <?php $this->extend('layout/app'); ?>
 *   <?php $this->section('content'); ?>
 *     <h1><?= e($title) ?></h1>
 *   <?php $this->end(); ?>
 *
 * Templates are sandboxed: only the helper methods of this class and the
 * data variables passed to render() are in scope.
 */

declare(strict_types=1);

namespace MdViewer\View;

use MdViewer\Core\Paths;
use MdViewer\Core\Request;

final class Template
{
    private string $templateDir;
    private ?string $layout = null;
    /** @var array<string, string> */
    private array $sections = [];
    /** @var list<string> */
    private array $stack = [];
    private ?Request $request = null;
    private string $nonce = '';

    public function __construct(?string $templateDir = null)
    {
        $this->templateDir = $templateDir ?? Paths::templates();
    }

    public function setRequest(Request $request): void
    {
        $this->request = $request;
    }

    public function setNonce(string $nonce): void
    {
        $this->nonce = $nonce;
    }

    public function nonce(): string
    {
        return $this->nonce;
    }

    public function request(): ?Request
    {
        return $this->request;
    }

    /**
     * Render a template (with layout inheritance) and return the HTML.
     *
     * @param array<string, mixed> $data
     */
    public function render(string $template, array $data = []): string
    {
        $this->layout = null;
        $content = $this->renderFile($template, $data);

        if ($this->layout !== null) {
            $layout = $this->layout;
            $this->layout = null;
            $this->sections['CONTENT'] = $content;

            return $this->renderFile($layout, array_merge($data, ['__sections' => $this->sections]));
        }

        return $content;
    }

    /** @param array<string, mixed> $data */
    private function renderFile(string $template, array $data): string
    {
        $path = $this->templateDir . '/' . ltrim($template, '/') . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException("Template not found: {$template} ({$path})");
        }

        $this->stack[] = $path;
        $level = ob_get_level();
        ob_start();
        // Sandbox: bind only $data keys + $this helpers.
        try {
            (function () use ($path, $data): void {
                extract($data, EXTR_SKIP);
                require $path;
            })->call($this);
        } catch (\Throwable $e) {
            while (ob_get_level() > $level) {
                ob_end_clean();
            }
            array_pop($this->stack);

            throw $e;
        }
        $html = (string) ob_get_clean();
        array_pop($this->stack);

        return $html;
    }

    // ── Template-side API ──────────────────────────────────────────────────

    protected function extend(string $layout): void
    {
        $this->layout = $layout;
    }

    protected function section(string $name): void
    {
        ob_start();
        $this->stack[] = '@section:' . $name;
    }

    protected function end(): string
    {
        $marker = array_pop($this->stack);
        $content = (string) ob_get_clean();
        if (is_string($marker) && str_starts_with($marker, '@section:')) {
            $this->sections[substr($marker, 9)] = $content;

            return '';
        }

        return $content;
    }

    /** Render a partial inline (no section capture). @param array<string, mixed> $data */
    protected function partial(string $template, array $data = []): string
    {
        return $this->renderFile($template, $data);
    }

    protected function yield(string $name, string $default = ''): string
    {
        return $this->sections[$name] ?? $default;
    }

    // ── Global helpers (also exposed as functions in bootstrap) ────────────

    public function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    public function icon(string $name, string $class = 'h-4 w-4', string $title = ''): string
    {
        return Icons::svg($name, $class, $title);
    }

    public function url(array $params = []): string
    {
        return $this->request?->url($params) ?? '?' . http_build_query($params);
    }

    public function asset(string $path): string
    {
        if ($this->request === null) {
            return '/assets/' . ltrim($path, '/');
        }
        $url = $this->request->assetUrl('assets/' . ltrim($path, '/'));
        $file = Paths::public() . '/assets/' . ltrim($path, '/');
        $mtime = is_file($file) ? (int) @filemtime($file) : 0;

        return $mtime > 0 ? $url . '?v=' . $mtime : $url;
    }
}
