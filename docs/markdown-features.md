# Markdown feature matrix

MD.Scribe 3 renders with `league/commonmark` (CommonMark 0.31 + GFM) plus
MD.Scribe extensions. Everything below works in the viewer, the clipboard
preview and the admin editor preview — one engine, one behaviour.

## CommonMark core

```markdown
# H1 … ###### H6        headings (auto ids, numbers, hover anchors)
**bold**  *italic*  ***both***  `code`  ~~strike~~
> blockquote            with copy control
- unordered   1. ordered    - [x] task lists
---                     thematic break
[link](url)  ![img](src "title")  <https://auto.link>
[ref]: https://ref.style "definitions anywhere"
```

- Hard breaks: two trailing spaces or `\`; soft breaks render as `<br>` when
  `DOUBLE_LINE_BREAKS` is on (MD.Viewer legacy default) — switch to classic
  paragraph spacing per-browser in Settings.
- Fenced code with ```` ``` ```` or `~~~`, any fence length (nested fences
  obey CommonMark §4.5 — the v2 fence-length fix is native here).
- Indented code blocks. Setext headings. HTML entities. Escapes `\*`.

## GitHub Flavored Markdown

| Syntax | Result | Toggle |
| --- | --- | --- |
| `\| a \| b \|` tables with `:---:` alignment | styled, scrollable tables | always |
| `~~strike~~` (and `~single~`) | `<del>` | always |
| `- [ ] / - [x]` | themed checkboxes | `FEATURE_TASK_LISTS` |
| bare URLs | autolinked | always |

## MD.Scribe extensions

### Frontmatter (YAML-lite)

```yaml
---
title: Overrides the first H1
description: Meta/OG description
tags: [docs, public]        # inline or block lists
author: Jane Doe
date: 2026-01-31
draft: true                 # hidden from library/search/feeds
toc: false                  # hide the TOC sidebar
numbering: false            # disable heading numbers
---
```

### Math (KaTeX, fully local)

```markdown
Inline $e^{i\pi}+1=0$ flows with text.

$$\int\limits_{-\infty}^{\infty} e^{-x^2}\,dx = \sqrt{\pi}$$

Chemistry via mhchem: $\ce{H2SO4 + 2NaOH -> Na2SO4 + 2H2O}$
```

Currency safety: `$5 and $10` stays prose (closing `$` followed by a digit
never starts math). `\$` escapes. `$$…$$` blocks may span lines and are
extracted before parsing, so code fences protect their dollars.

### Mermaid diagrams (local, auto-repaired)

````markdown
```mermaid
graph TD
    A(Start) --> B{Decision}
    B -->|Yes| C(Finish (x))
```
````

The **server-side repair engine** (v2.5.2 rules, ported and extended) fixes
before rendering: PlantUML class-diagram `note … end note` blocks, literal
`\n` in labels, unclosed `<br>`, PlantUML `return` in sequence diagrams, and
unquoted parentheses in node labels — round shapes now get *balance-aware*
matching, an improvement over v2. Applied repairs are listed under each
diagram (hover for per-line detail). Parse failures keep the source visible
and copyable with the error message.

### GitHub-style alerts

```markdown
> [!NOTE] / [!TIP] / [!IMPORTANT] / [!WARNING] / [!CAUTION]
> Callout body — full Markdown works inside.
```

### Footnotes

```markdown
Reference[^1] and inline^[written right at the reference.] forms.

[^1]: Collected at the bottom with backlinks (↩) and ARIA roles.
```

### Definition lists

```markdown
Term
: Definition (PHP-Markdown style). Multiple terms and definitions allowed.
```

### Superscript, subscript, marks

```markdown
H~2~O   x^2^   ==highlighted==
```

### Emoji

`:rocket:` `:tada:` `:+1:` — the full GitHub gemoji database (1,913 aliases).
Unknown `:codes:` and time strings like `12:30:45` pass through untouched.

### Attributes

```markdown
## Heading {#custom-id .extra-class}
![img](src){width=300}
```

### Smart punctuation (opt-in)

`FEATURE_SMART_PUNCT = true` → `"quotes"` → curly, `--` → en-dash, `...` →
ellipsis.

### Universal pattern chains (v2 legacy)

````markdown
`(Client) --> [HTTP/1.1: uses] --> (Server)`
````

Code spans that read like node/relation chains render as a small visual
chain (nodes, labelled arrows). Plain code is untouched; the v2.16
infinite-loop input (`--basetemp=<path>`) is a regression test.

### Glossary tooltips

Any table whose **first column has a header** becomes a glossary:

```markdown
| Term | Definition |
| ---- | ---------- |
| CSP (Content Security Policy) | A browser policy header… |
```

Occurrences of `CSP`, `Content Security Policy` and the combined form get
dotted underlines; hovering/tapping/focusing shows a tooltip built **live**
from the table cells (aliases expand automatically). Content inside code,
links, `<th>` and the glossary's own first column is never tagged.

## Code blocks

````markdown
```php title="src/Demo.php"
<?php echo "hi";
```
````

- Server-side highlighting (~190 languages via highlight.php) — no client JS,
  highlighted HTML is cached with the document.
- Bar shows language or `title="…"`, with **copy** and **word-wrap** controls.
- Unknown languages fall back to escaped plain text (never an error page).

## Links & media behaviour

| Input | Result |
| --- | --- |
| `[x](other.md)` | rewritten to the viewer route `?file=…` (resolved against the document's directory, `../` supported) |
| `[x](missing.md)` | same, plus a dashed-red **missing-link** marker |
| `![](img/pic.png)` | resolved against the document and served via `?media=…` (works even when content is outside the web root) |
| `[x](https://…)` | new tab, `rel="noopener noreferrer"` |
| `[x](#heading)` | in-page anchor |
| `[x](javascript:…)` | rendered as plain text (blocked at parser **and** sanitizer level) |

## Raw HTML

Escaped by default. With `ALLOW_RAW_HTML = true` author HTML survives but is
still sanitized: allowlisted tags/attributes only, no event handlers, no
forms, inert-graphics SVG only, `iframe/video/audio` additionally require
`ALLOW_IFRAMES`. `<details>/<summary>`, `<kbd>`, `<mark>`, `<abbr>` work.

## In-document TOC

A line containing only `[TOC]` inserts an inline table of contents at that
position (the sidebar TOC is separate).
