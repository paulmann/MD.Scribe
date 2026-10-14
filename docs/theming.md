# Theming

MD.Scribe ships a shadcn/ui-style theming system: **design tokens as CSS
custom properties**, composed from a neutral palette × an accent color, with
light/dark/system modes. No CDN, no runtime framework — the theme stylesheet
is generated at build time and selected by data attributes on `<html>`.

## Visitor-facing controls

- **Header dropdown** — light / dark / system + a shortcut to the catalog.
- **Themes page** (`?page=themes`) — 12 curated presets rendered as live
  mini-previews, plus the *mixer*: any neutral (Zinc, Slate, Stone, Gray,
  Neutral) × any accent (Mono, Blue, Violet, Rose, Green, Orange, Teal, Red,
  Amber, Pink, Indigo, Cyan, + admin-saved customs), corner radius
  (none/sm/md/lg/xl) and font (Inter / System / Serif), with a live sample.
- **Persistence** — `localStorage['mdv.theme']`, applied by a pre-paint
  bootstrap script (nonce'd, CSP-safe): no flash of the wrong theme, and
  `system` mode follows the OS live via `matchMedia`.

First-visit defaults come from `.md.ini`: `THEME_DEFAULT` (preset id) and
`MODE_DEFAULT` (`light|dark|system`).

## Token model

Identical to shadcn/ui — any shadcn recipe ports 1:1:

```css
--background --foreground
--card --card-foreground
--popover --popover-foreground
--primary --primary-foreground
--secondary --secondary-foreground
--muted --muted-foreground
--accent --accent-foreground
--destructive --destructive-foreground
--border --input --ring --radius
--chart-1 … --chart-5
--sidebar --sidebar-foreground --sidebar-primary … --sidebar-ring
```

Colors are **oklch()** (perceptually uniform, graceful dark-mode mapping).
Tailwind v4 maps them via `@theme inline` (`bg-background`, `text-primary`,
`border-border` … work as utilities).

Selection mechanics — every element can carry a theme (the whole page, or a
single preview card):

```html
<html class="dark" data-neutral="slate" data-accent="blue"
      data-radius="lg" data-font="inter">
```

```css
[data-neutral="slate"] { --background: …; }                 /* light */
[data-neutral="slate"].dark, [data-neutral="slate"].mdv-dark { … }  /* dark */
[data-accent="blue"]   { --primary: …; --ring: …; }
[data-radius="lg"]     { --radius: 0.875rem; }
[data-font="serif"]    { --font-sans: ui-serif, Georgia, …; }
```

`.mdv-dark` mirrors `.dark` for scoped previews (theme cards show dark
tokens even while the page is light).

## Custom themes (admin)

**Admin → Custom themes**: name + light/dark primary + on-primary colors →
saved to `storage/themes/custom-<slug>.json`, validated against a color-token
allowlist (`oklch()/hsl()/rgb()/#hex` only), injected as a small runtime
`<style>` block, and listed in the public accent picker. Delete at any time.

Programmatic API:

```php
$catalog = new MdViewer\Theme\ThemeCatalog();
$catalog->presets();            // gallery data incl. swatches
$catalog->saveCustom($id, $name, $light, $dark);
$catalog->customCss();          // runtime CSS for saved customs
$catalog->buildCss();           // full themes.css (build time)
```

## Reading preferences (orthogonal to themes)

Text size (85–130 %), page width (narrow/comfortable/wide/full) and line
height (1.5–2.1) are per-visitor settings stored in
`localStorage['mdv.prefs']`, applied as CSS variables on `:root`
(`--md-reader-scale`, `--md-reader-width`, `--md-reader-leading`) **via
CSSOM** — CSP-safe, no inline styles.

## Adding a built-in theme

Edit `config/themes.php` (neutrals / accents / presets), then rebuild:

```bash
php tools/build-themes.php      # → public/assets/css/themes.css
```

Guidelines: keep oklch lightness ~0.14–0.21 for dark backgrounds and ~0.98–1
for light; accent dark primaries one step lighter than light primaries;
on-primary text must pass WCAG AA against the primary in both modes.

## Print

A dedicated print stylesheet hides chrome (header, sidebars, buttons,
toasts), forces black-on-white, expands code wrapping and appends URLs after
external links.

## Reduced motion & accessibility

- `prefers-reduced-motion` disables animations globally.
- All controls are real `<button>`s with roles (`menu`, `switch`, `radio`),
  keyboard-operable, with visible focus rings from `--ring`.
- Theme choices never affect semantics: contrast pairs are curated per token.
