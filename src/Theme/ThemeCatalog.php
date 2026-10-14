<?php

/**
 * MD.Scribe 3.0 — theme catalog.
 *
 * Composes neutral × accent token sets into CSS custom properties following
 * the shadcn/ui model. The generated stylesheet uses attribute selectors
 * ([data-neutral=…], [data-accent=…]) so ANY element can carry a theme —
 * the <html> root for the site, or a single preview card on the Themes page.
 * Dark variants activate via the .dark / .mdv-dark class on the same element.
 *
 * Built-in combinations are compiled into public/assets/css/themes.css at
 * build time (tools/build-themes.php); user-saved custom accents (stored as
 * JSON in storage/themes) are emitted as a small runtime <style> block.
 */

declare(strict_types=1);

namespace MdViewer\Theme;

final class ThemeCatalog
{
    /** @var array<string, mixed>|null */
    private ?array $data = null;

    public function __construct(
        private readonly string $configFile = '',
        private readonly string $customDir = '',
    ) {
    }

    /** @return array<string, mixed> */
    public function data(): array
    {
        if ($this->data === null) {
            $file = $this->configFile !== '' ? $this->configFile : \MdViewer\Core\Paths::config() . '/themes.php';
            /** @var array<string, mixed> $data */
            $data = require $file;
            $this->data = $data;
        }

        return $this->data;
    }

    /** @return list<array<string, mixed>> */
    public function presets(): array
    {
        $presets = [];
        foreach ((array) ($this->data()['presets'] ?? []) as $preset) {
            $neutral = $this->neutral((string) ($preset['neutral'] ?? 'zinc'));
            $accent = $this->accent((string) ($preset['accent'] ?? 'default'));
            $presets[] = $preset + [
                'swatch_light' => $neutral['light']['background'] ?? '#fff',
                'swatch_dark' => $neutral['dark']['background'] ?? '#111',
                'swatch_accent_light' => $accent['light']['primary'] ?? ($neutral['light']['primary'] ?? '#333'),
                'swatch_accent_dark' => $accent['dark']['primary'] ?? ($neutral['dark']['primary'] ?? '#eee'),
                'neutral_name' => $neutral['name'] ?? '',
                'accent_name' => $accent['name'] ?? '',
            ];
        }

        return $presets;
    }

    /** @return list<array{id: string, name: string}> */
    public function neutralChoices(): array
    {
        $out = [];
        foreach ((array) ($this->data()['neutrals'] ?? []) as $id => $neutral) {
            $out[] = ['id' => (string) $id, 'name' => (string) ($neutral['name'] ?? $id)];
        }

        return $out;
    }

    /** @return list<array{id: string, name: string}> */
    public function accentChoices(): array
    {
        $out = [];
        foreach ((array) ($this->data()['accents'] ?? []) as $id => $accent) {
            $out[] = ['id' => (string) $id, 'name' => (string) ($accent['name'] ?? $id)];
        }
        foreach ($this->listCustom() as $custom) {
            $out[] = ['id' => $custom['id'], 'name' => $custom['name'] . ' (custom)'];
        }

        return $out;
    }

    public function neutral(string $id): array
    {
        $neutrals = (array) ($this->data()['neutrals'] ?? []);

        return (array) ($neutrals[$id] ?? $neutrals['zinc'] ?? []);
    }

    public function accent(string $id): array
    {
        $accents = (array) ($this->data()['accents'] ?? []);
        if (isset($accents[$id])) {
            return (array) $accents[$id];
        }
        foreach ($this->listCustom() as $custom) {
            if ($custom['id'] === $id) {
                return $custom;
            }
        }

        return ['name' => 'Mono', 'inherit' => true];
    }

    public function preset(string $id): ?array
    {
        foreach ((array) ($this->data()['presets'] ?? []) as $preset) {
            if (($preset['id'] ?? null) === $id) {
                return (array) $preset;
            }
        }

        return null;
    }

    public function radiusValue(string $id): string
    {
        $radii = (array) ($this->data()['radii'] ?? []);

        return (string) ($radii[$id] ?? $radii['md'] ?? '0.625rem');
    }

    public function fontStack(string $id): string
    {
        $fonts = (array) ($this->data()['fonts'] ?? []);

        return (string) ($fonts[$id]['sans'] ?? $fonts['inter']['sans'] ?? 'system-ui, sans-serif');
    }

    // ── Custom (user-saved) accents ────────────────────────────────────────

    /** @return list<array{id: string, name: string, light: array, dark: array}> */
    public function listCustom(): array
    {
        $dir = $this->customDir !== '' ? $this->customDir : \MdViewer\Core\Paths::customThemes();
        $out = [];
        foreach ((array) glob($dir . '/*.json') as $file) {
            if (!is_string($file)) {
                continue;
            }
            $raw = (string) @file_get_contents($file);
            if (!json_validate($raw)) {
                continue;
            }
            $data = json_decode($raw, true);
            if (!is_array($data) || !is_string($data['id'] ?? null)) {
                continue;
            }
            $out[] = $data;
        }

        return $out;
    }

    public function saveCustom(string $id, string $name, array $light, array $dark): array
    {
        $id = 'custom-' . preg_replace('/[^a-z0-9-]+/i', '-', $id);
        $id = strtolower(substr((string) $id, 0, 40));
        $dir = $this->customDir !== '' ? $this->customDir : \MdViewer\Core\Paths::customThemes();
        if (!is_dir($dir)) {
            @mkdir($dir, 0o755, true);
        }
        $data = [
            'id' => $id,
            'name' => mb_substr($name, 0, 40),
            'custom' => true,
            'light' => self::filterColorTokens($light),
            'dark' => self::filterColorTokens($dark),
        ];
        file_put_contents($dir . '/' . $id . '.json', (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);

        return $data;
    }

    public function deleteCustom(string $id): bool
    {
        if (!preg_match('/^custom-[a-z0-9-]{1,36}$/', $id)) {
            return false;
        }
        $dir = $this->customDir !== '' ? $this->customDir : \MdViewer\Core\Paths::customThemes();
        $file = $dir . '/' . $id . '.json';

        return is_file($file) && @unlink($file);
    }

    /** Only shadcn color tokens with safe values survive. */
    private static function filterColorTokens(array $tokens): array
    {
        $allowed = ['primary', 'primary-foreground', 'ring'];
        $out = [];
        foreach ($allowed as $key) {
            $value = (string) ($tokens[$key] ?? '');
            if ($value !== '' && preg_match('/^(oklch|hsl|rgb)a?\([\d\s.,%\/deg-]+\)$|^#[0-9a-fA-F]{3,8}$/', $value) === 1) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    // ── CSS generation ─────────────────────────────────────────────────────

    /** Full themes.css content for all built-ins + customs. */
    public function buildCss(): string
    {
        $css = "/* MD.Scribe themes — generated by tools/build-themes.php. Do not edit. */\n";
        $charts = (array) ($this->data()['charts'] ?? []);

        $css .= ":root {\n";
        foreach ($charts as $name => $value) {
            $css .= '  --' . $name . ': ' . $value . ";\n";
        }
        $css .= "  --sidebar: var(--card);\n  --sidebar-foreground: var(--foreground);\n";
        $css .= "  --sidebar-primary: var(--primary);\n  --sidebar-primary-foreground: var(--primary-foreground);\n";
        $css .= "  --sidebar-accent: var(--accent);\n  --sidebar-accent-foreground: var(--accent-foreground);\n";
        $css .= "  --sidebar-border: var(--border);\n  --sidebar-ring: var(--ring);\n}\n";

        foreach ((array) ($this->data()['neutrals'] ?? []) as $id => $neutral) {
            $css .= $this->tokenBlock('[data-neutral="' . $id . '"]', (array) ($neutral['light'] ?? []));
            $css .= $this->tokenBlock(
                '[data-neutral="' . $id . '"].dark, [data-neutral="' . $id . '"].mdv-dark',
                (array) ($neutral['dark'] ?? []),
            );
        }

        foreach ((array) ($this->data()['accents'] ?? []) as $id => $accent) {
            if (($accent['inherit'] ?? false) === true) {
                continue;
            }
            $css .= $this->tokenBlock('[data-accent="' . $id . '"]', (array) ($accent['light'] ?? []));
            $css .= $this->tokenBlock(
                '[data-accent="' . $id . '"].dark, [data-accent="' . $id . '"].mdv-dark',
                (array) ($accent['dark'] ?? []),
            );
        }

        foreach ($this->listCustom() as $custom) {
            $id = (string) $custom['id'];
            $css .= $this->tokenBlock('[data-accent="' . $id . '"]', (array) ($custom['light'] ?? []));
            $css .= $this->tokenBlock(
                '[data-accent="' . $id . '"].dark, [data-accent="' . $id . '"].mdv-dark',
                (array) ($custom['dark'] ?? []),
            );
        }

        foreach ((array) ($this->data()['radii'] ?? []) as $id => $value) {
            $css .= '[data-radius="' . $id . '"] { --radius: ' . $value . "; }\n";
        }
        foreach ((array) ($this->data()['fonts'] ?? []) as $id => $font) {
            $css .= '[data-font="' . $id . '"] { --font-sans: ' . $font['sans'] . "; }\n";
        }

        return $css;
    }

    /** Runtime CSS for custom accents saved after the last build. */
    public function customCss(): string
    {
        $css = '';
        foreach ($this->listCustom() as $custom) {
            $id = (string) $custom['id'];
            $css .= $this->tokenBlock('[data-accent="' . $id . '"]', (array) ($custom['light'] ?? []));
            $css .= $this->tokenBlock(
                '[data-accent="' . $id . '"].dark, [data-accent="' . $id . '"].mdv-dark',
                (array) ($custom['dark'] ?? []),
            );
        }

        return $css;
    }

    private function tokenBlock(string $selector, array $tokens): string
    {
        if ($tokens === []) {
            return '';
        }
        $block = $selector . " {\n";
        foreach ($tokens as $name => $value) {
            if (!is_string($name) || !is_scalar($value)) {
                continue;
            }
            $block .= '  --' . htmlspecialchars((string) $name, ENT_QUOTES) . ': ' . htmlspecialchars((string) $value, ENT_QUOTES) . ";\n";
        }

        return $block . "}\n";
    }
}
