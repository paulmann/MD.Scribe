/**
 * MD.Viewer 3.0 — regenerate src/View/Icons.php from lucide-static using the
 * curated list in tools/frontend/icons.json.
 *
 * Run: node tools/frontend/generate-icons.mjs
 */
import { readFileSync, writeFileSync, existsSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '../..');
const names = JSON.parse(readFileSync(join(here, 'icons.json'), 'utf8'));
const dir = join(here, 'node_modules/lucide-static/icons');

const map = {};
const missing = [];
for (const n of names) {
  const f = join(dir, `${n}.svg`);
  if (!existsSync(f)) { missing.push(n); continue; }
  map[n] = readFileSync(f, 'utf8').replace(/<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '').trim();
}
if (missing.length) console.warn('missing icons:', missing.join(', '));
if (!map['circle-alert']) map['circle-alert'] = map['info'] ?? '';

const entries = Object.entries(map)
  .map(([k, v]) => `        '${k}' => '${v.replace(/\\/g, '\\\\').replace(/'/g, "\\'")}',`)
  .join('\n');

const php = `<?php

/**
 * This file is AUTO-GENERATED from lucide-static (https://lucide.dev, ISC
 * License) by tools/frontend/generate-icons.mjs. Do not edit by hand.
 */

declare(strict_types=1);

namespace MdViewer\\View;

final class Icons
{
    /** @var array<string, string>|null */
    private static ?array $map = null;

    /** @return array<string, string> icon name => inner SVG markup (24x24 viewBox) */
    public static function all(): array
    {
        return self::$map ??= [
${entries}
        ];
    }

    public static function has(string $name): bool
    {
        return isset(self::all()[$name]);
    }

    /** Render an inline <svg> icon. */
    public static function svg(string $name, string $class = 'h-4 w-4', string $title = ''): string
    {
        $inner = self::all()[$name] ?? self::all()['circle-alert'] ?? '';
        $titleTag = $title !== '' ? '<title>' . htmlspecialchars($title, ENT_QUOTES) . '</title>' : '';
        $aria = $title !== '' ? ' aria-hidden="false" role="img"' : ' aria-hidden="true"';

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" '
            . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" '
            . 'stroke-linejoin="round" class="' . htmlspecialchars($class, ENT_QUOTES) . '"'
            . $aria . '>' . $titleTag . $inner . '</svg>';
    }
}
`;

writeFileSync(join(root, 'src/View/Icons.php'), php);
console.log(`Icons.php generated with ${Object.keys(map).length} icons`);
