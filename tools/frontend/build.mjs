/**
 * MD.Scribe 3.0 — asset build pipeline (dev-time only; outputs are committed,
 * so production needs no Node.js at all).
 *
 *   node build.mjs
 *
 * Steps:
 *  1. Tailwind CSS v4      src/css/app.css        → public/assets/css/app.css
 *  2. copy                 src/css/typography.css → public/assets/css/typography.css
 *  3. esbuild (bundle)     src/js/main.js         → public/assets/js/app.js
 *  4. esbuild (bundle)     src/js/admin-main.js   → public/assets/js/admin.js
 *  5. php tools/build-themes.php                  → public/assets/css/themes.css
 *  6. vendor refresh (mermaid / katex / fonts / icons / emoji) with --vendor
 */

import { execSync } from 'node:child_process';
import { copyFileSync, existsSync, mkdirSync, readFileSync, writeFileSync, readdirSync, rmSync } from 'node:fs';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import * as esbuild from 'esbuild';

const here = dirname(fileURLToPath(import.meta.url));
const root = resolve(here, '../..');
const outCss = join(root, 'public/assets/css');
const outJs = join(root, 'public/assets/js');
const withVendor = process.argv.includes('--vendor');

mkdirSync(outCss, { recursive: true });
mkdirSync(outJs, { recursive: true });

function step(name, fn) {
  process.stdout.write(`▸ ${name} … `);
  const t0 = Date.now();
  fn();
  process.stdout.write(`${Date.now() - t0}ms\n`);
}

step('tailwind css', () => {
  execSync('npx tailwindcss -i src/css/app.css -o ../../public/assets/css/app.css --minify', { cwd: here, stdio: 'pipe' });
});

step('typography css', () => {
  copyFileSync(join(here, 'src/css/typography.css'), join(outCss, 'typography.css'));
});

step('admin css', () => {
  copyFileSync(join(here, 'src/css/admin.css'), join(outCss, 'admin.css'));
});

step('bundle app.js', async () => {});
await esbuild.build({
  entryPoints: [join(here, 'src/js/main.js')],
  outfile: join(outJs, 'app.js'),
  bundle: true, minify: true, format: 'iife', target: ['es2020'],
  legalComments: 'none',
});

if (existsSync(join(here, 'src/js/admin-main.js'))) {
  await esbuild.build({
    entryPoints: [join(here, 'src/js/admin-main.js')],
    outfile: join(outJs, 'admin.js'),
    bundle: true, minify: true, format: 'iife', target: ['es2020'],
    legalComments: 'none',
  });
  process.stdout.write('▸ bundle admin.js … done\n');
}

step('themes.css', () => {
  execSync('php tools/build-themes.php', { cwd: root, stdio: 'pipe' });
});

if (withVendor) {
  step('vendor assets', () => {
    const nm = join(here, 'node_modules');
    const vendor = join(root, 'public/assets/vendor');
    mkdirSync(join(vendor, 'mermaid'), { recursive: true });
    mkdirSync(join(vendor, 'katex/contrib'), { recursive: true });
    mkdirSync(join(vendor, 'katex/fonts'), { recursive: true });
    mkdirSync(join(vendor, 'fonts'), { recursive: true });

    copyFileSync(join(nm, 'mermaid/dist/mermaid.min.js'), join(vendor, 'mermaid/mermaid.min.js'));

    const katexDist = join(nm, 'katex/dist');
    copyFileSync(join(katexDist, 'katex.min.css'), join(vendor, 'katex/katex.min.css'));
    copyFileSync(join(katexDist, 'katex.min.js'), join(vendor, 'katex/katex.min.js'));
    copyFileSync(join(katexDist, 'contrib/auto-render.min.js'), join(vendor, 'katex/contrib/auto-render.min.js'));
    copyFileSync(join(katexDist, 'contrib/mhchem.min.js'), join(vendor, 'katex/contrib/mhchem.min.js'));
    for (const f of readdirSync(join(katexDist, 'fonts'))) {
      if (f.endsWith('.woff2')) copyFileSync(join(katexDist, 'fonts', f), join(vendor, 'katex/fonts', f));
    }

    const interFiles = join(nm, '@fontsource-variable/inter/files');
    for (const subset of ['latin', 'latin-ext', 'cyrillic', 'cyrillic-ext']) {
      for (const style of ['normal', 'italic']) {
        const f = `inter-${subset}-wght-${style}.woff2`;
        if (existsSync(join(interFiles, f))) copyFileSync(join(interFiles, f), join(vendor, 'fonts', f));
      }
    }
    const jbmFiles = join(nm, '@fontsource-variable/jetbrains-mono/files');
    for (const subset of ['latin', 'latin-ext', 'cyrillic']) {
      for (const style of ['normal', 'italic']) {
        const f = `jetbrains-mono-${subset}-wght-${style}.woff2`;
        if (existsSync(join(jbmFiles, f))) copyFileSync(join(jbmFiles, f), join(vendor, 'fonts', f));
      }
    }

    // Icons → PHP map
    const wanted = JSON.parse(readFileSync(join(here, 'icons.json'), 'utf8'));
    const iconDir = join(nm, 'lucide-static/icons');
    const map = {};
    const missing = [];
    for (const name of wanted) {
      const f = join(iconDir, `${name}.svg`);
      if (!existsSync(f)) { missing.push(name); continue; }
      const svg = readFileSync(f, 'utf8');
      map[name] = svg.replace(/<svg[^>]*>/, '').replace(/<\/svg>\s*$/, '').trim();
    }
    if (missing.length) console.warn(`  icons missing: ${missing.join(', ')}`);
    const entries = Object.entries(map).map(([k, v]) => `        '${k}' => '${v.replace(/'/g, "\\'")}',`);
    writeFileSync(join(root, 'src/View/Icons.php'), `<?php

/**
 * This file is AUTO-GENERATED by tools/frontend/build.mjs from lucide-static
 * (https://lucide.dev, ISC License). Do not edit by hand.
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
${entries.join('\n')}
        ];
    }

    public static function has(string $name): bool
    {
        return isset(self::all()[$name]);
    }

    /** Render an inline <svg> icon. */
    public static function svg(string $name, string $class = 'h-4 w-4', string $title = ''): string
    {
        $inner = self::all()[$name] ?? self::all()['circle-alert'];
        $titleTag = $title !== ''
            ? '<title>' . htmlspecialchars($title, ENT_QUOTES) . '</title>'
            : '';
        $aria = $title !== '' ? ' aria-hidden="false" role="img"' : ' aria-hidden="true"';
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" '
            . 'stroke="currentColor" stroke-width="2" stroke-linecap="round" '
            . 'stroke-linejoin="round" class="' . htmlspecialchars($class, ENT_QUOTES) . "\\"{$aria}>"
            . $titleTag . $inner . '</svg>';
    }
}
`);
    console.log(`  icons: ${Object.keys(map).length}`);
  });
}

console.log('✔ build complete');
