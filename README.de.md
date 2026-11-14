<!--
 * MD.Scribe — Documentation
 * Version: 3.6.0
-->

# MD.Scribe 3

MD.Scribe ist eine selbst gehostete Markdown-Dokumentationsplattform in PHP 8.5: ein
CommonMark/GFM-vollständiger Viewer mit Volltextsuche, Befehlspalette, Theme-Katalog,
Mermaid-Diagrammen und KaTeX-Formeln sowie ein Adminbereich mit Inhaltsverwaltung, Editor,
Sicherungen, geprüftem Selbst-Updater und Audit-Trail. Es braucht keine Datenbank, kein
Framework und keinen Build-Schritt auf dem Server; alle Assets liegen lokal, sodass die
gesamte Anwendung offline arbeitet.

<!-- md-i18n:switcher -->
**In anderen Sprachen:** [English](README.md) · Deutsch · [Español](README.es.md) · [Français](README.fr.md) · [हिन्दी](README.hi.md) · [中文](README.zh.md) · [Русский](README.ru.md)

[![CI](https://github.com/paulmann/MD.Scribe/actions/workflows/ci.yml/badge.svg)](https://github.com/paulmann/MD.Scribe/actions/workflows/ci.yml)
[![Veröffentlichung](https://img.shields.io/github/v/release/paulmann/MD.Scribe?label=release&sort=semver)](https://github.com/paulmann/MD.Scribe/releases)
[![Lizenz: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://php.net)
[![Tests](https://img.shields.io/badge/tests-220%20passed-brightgreen)](tests)
[![CDN-Anfragen](https://img.shields.io/badge/CDN%20requests-0-success)](#zero-cdn-richtlinie)

<img src="https://github.com/paulmann/MD.Scribe/raw/main/assets/social-preview.png"
     alt="MD.Scribe — selbst gehostete Markdown-Dokumentationsplattform in PHP 8.5 mit Viewer und Adminbereich"
     width="100%">

[**Schnellstart**](#schnellstart) · [**Funktionsübersicht**](#funktionsübersicht) · [**Konfiguration**](#konfiguration--mdini) · [**Dokumentation**](#dokumentation) · [**Wiki**](https://github.com/paulmann/MD.Scribe/wiki) · [**AGENTS.md**](AGENTS.md) · [**Changelog**](CHANGELOG.md) · [**Sicherheit**](SECURITY.md) · [**Mitwirken**](CONTRIBUTING.md)

> **Dokumentationsstand.** Version und jede Zahl in dieser README wurden am 2026-11-14 gegen
> den `main`-Baum von 3.6.0 gemessen, wobei der Befehl jeweils dort genannt wird, wo die Zahl
> erscheint. Aufgabenorientierte Anleitungen liegen im
> [Wiki](https://github.com/paulmann/MD.Scribe/wiki/Home); die Referenzdokumentation liegt in
> [`docs/`](docs/installation.md).

## Inhalt

- [Was ist MD.Scribe?](#was-ist-mdscribe)
- [Schnellstart](#schnellstart)
- [Funktionsübersicht](#funktionsübersicht)
- [Konfiguration — `.md.ini`](#konfiguration--mdini)
- [URL-Vertrag](#url-vertrag)
- [Einbetten und CLI-Rendering](#einbetten-und-cli-rendering)
- [Sicherheit](#sicherheit)
- [In Zahlen](#in-zahlen)
- [Entwicklung](#entwicklung)
- [Dokumentation](#dokumentation)
- [Sprachen der Dokumentation](#sprachen-der-dokumentation)
- [Aktualisierung von v2](#aktualisierung-von-v2)
- [Mitwirken](#mitwirken)
- [Lizenz](#lizenz)
- [Dem Projekt helfen: Belege statt Interaktion](#dem-projekt-helfen-belege-statt-interaktion)

---

## Was ist MD.Scribe?

Richtet man es auf einen Ordner mit `.md`-Dateien, entsteht eine Dokumentationsseite:

- **`public/index.php`** rendert ein Dokument (`?file=path/doc.md`) und, wenn keine Datei
  angefragt wird, eine durchsuchbare **Bibliothek**, außerdem die Seiten **Themes**,
  **Einstellungen** und **Suche**, `sitemap.xml` und einen RSS-Feed.
- **`public/admin.php`** ist der Adminbereich: Dashboard, Dateimanager, geteilter Editor mit
  Live-Vorschau, Uploads, Steuerung des Suchindex, Sicherungen & Papierkorb, Selbst-Updater,
  Einstellungseditor, Logs, Audit-Trail, Sicherheits- und Zustandsprüfungen sowie Werkzeuge
  (Prüfung auf defekte Links, Cache-Steuerung).
- **`md.php` / `updater.php`** im Projektstamm sind Kompatibilitätsshim — jeder URL-Vertrag
  aus v2 (`?file=`, `&download=1`, `?update=true`, `?restore=latest`, die Maschinen-API)
  funktioniert nach einer Aktualisierung weiter.

**Neu hier?** Beginne mit dem [Wiki](https://github.com/paulmann/MD.Scribe/wiki/Getting-Started)
([Startseite](https://github.com/paulmann/MD.Scribe/wiki/Home)) — es führt vom Release-Archiv in
vier Befehlen zu einer geprüften Installation.

Alles wird von einem **Designsystem aus shadcn/ui-Tokens auf einem lokalen Build von
Tailwind CSS v4** gestaltet: Hell-, Dunkel- und Systemmodus, 12 kuratierte Themes und ein
Mixer aus Neutralton × Akzentfarbe. Mermaid, KaTeX, Inter, JetBrains Mono und alle Icons sind
mitgeliefert — die Anwendung arbeitet vollständig **offline / air-gapped**.

---

## Schnellstart

```bash
git clone https://github.com/paulmann/MD.Scribe.git /var/www/docs
cd /var/www/docs

# PHP dependencies are vendored in release archives; from a bare clone:
composer install --no-dev --optimize-autoloader

# put your documents in content/ (or set BROWSE_DIR in .md.ini)
cp ~/my-notes/*.md content/

# develop:
php -S localhost:8080 -t public public/router.php
# → http://localhost:8080        (viewer)
# → http://localhost:8080/admin.php   (admin — first-run setup)
```

Produktivinstallationen (Docroot auf `public/`, Docroot im Projektstamm, Apache, nginx,
Caddy, Docker, Shared Hosting): **[docs/installation.md](docs/installation.md)**.

Voraussetzungen: **PHP ≥ 8.5** mit `mbstring`, `dom`, `libxml`, `json`
(`curl` + `phar` + `zip` empfohlen für den Updater und die Sicherungsarchive).

---

## Funktionsübersicht

### Leseerlebnis

| Bereich | Funktionen |
| --- | --- |
| Typografie | Fluide Typoskala, Regler für Textgröße / Seitenbreite / Zeilenhöhe, Optionen für Serif- und Systemschriften, Druckstylesheet |
| Navigation | Automatisches Inhaltsverzeichnis mit Scrollspy, Überschriftennummern + Hover-Anker, `[TOC]`-Platzhalter, Breadcrumbs, Vor-/Zurück-Blättern, Ordner-Sidebar, Skip-Links |
| Auffinden | Serverseitige **Volltextsuche** (invertierter Index, CJK-fähig) mit **Operatoren** (`tag:`, `path:`, Werten in Anführungszeichen), **Befehlspalette mit Ctrl+K** (Dokumente, Überschriften, Aktionen), **Tag-Index** (`?page=tags`), sitemap.xml, RSS-Feed |
| Reichhaltige Inhalte | Serverseitiges **Syntax-Highlighting** (~190 Sprachen), **Mermaid**-Diagramme mit Auto-Reparatur + Diagnose, **KaTeX**-Formeln (inkl. mhchem), **Alerts** im GitHub-Stil, Aufgabenlisten, Fußnoten, Definitionslisten, `==marks==`, ^super^/~sub~script, 1.900+ Emoji-Kurzwörter, **Lightbox** für Bilder mit Bildunterschriften, geschätzte Lesezeit |
| Wissensbasis | **Wikilinks** (`[[Page]]`, `[[Page\|Alias]]`, `[[Page#Heading]]`) mit Markern für fehlende Seiten, **Backlinks** („Verlinkt von“), Frontmatter-**Tags** mit öffentlichem Index, **Versionshistorie** pro Dokument mit Wiederherstellung, **statischer Site-Export** in ein eigenständiges ZIP |
| Glossar | Jede zweispaltige Tabelle wird zu **Tooltips** bei Hover/Tippen mit Alias-Erweiterung (`Term (ABBR)`), Inhalt live aus dem DOM — ohne Duplizierung |
| Offline | Installierbare **PWA**: Web-Manifest + Service Worker — Assets cache-first, Dokumente network-first mit der zuletzt gelesenen Fassung als Rückfall, JSON-APIs nie gecacht (`PWA_ENABLED`) |
| Themes | 12 Presets + Mixer aus Neutralton × Akzentfarbe × Radius × Schrift, Hell/Dunkel/**automatische Systemerkennung**, Persistenz je Besucher, über den Adminbereich verwaltete **eigene Themes**, FOUC-freier Bootstrap vor dem ersten Paint |

### Markdown-Unterstützung

CommonMark **vollständig nach 0.31** über `league/commonmark`, dazu GFM (Tabellen mit
Ausrichtung, Durchstreichen, Aufgabenlisten, Autolinks), Fußnoten (als Referenz **und** inline),
Definitionslisten, Attributsyntax `{#id .class}`, intelligente Interpunktion (optional),
YAML-Frontmatter (`title`, `description`, `tags`, `author`, `date`, `draft`, `toc`, `numbering`),
Umschreiben relativer `.md`-Links mit Markern für tote Links, `[[Wikilinks]]` mit Auflösung von
Alias und Anker sowie Extras aus MD.Viewer (universelle Pattern-Ketten, Glossar-Tabellen,
Namensregel `md.md`). Vollständige Matrix:
**[docs/markdown-features.md](docs/markdown-features.md)**.

### Adminbereich

| Seite | Funktion |
| --- | --- |
| **Dashboard** | Korpus-Statistik, Aktualität des Index, Zustandsübersicht, Schnellaktionen, zuletzt bearbeitete Dokumente, Aktivitäts-Feed |
| **Dateien** | Baum aller Inhalte, Filter, Anlegen/Umbenennen/Verschieben/Löschen (Soft-Delete in den **Papierkorb**), Ordner, Mehrfach-Upload (.md + Bilder mit Content-Sniffing), **neu aus Vorlage** |
| **Editor** | Live-Vorschau im geteilten Fenster (die *echte* Engine), **Markdown-Werkzeugleiste** (fett, kursiv, Code, Link, Wikilink, Liste, Alert, Tabelle), Speichern mit ⌘S und **sha256-Konflikterkennung**, Wort- und Lesezeitzähler, Bild-Upload an der Cursorposition, Deep Links `?page=editor&file=…` |
| **Suchindex** | Statistik, Aktualität, Neuaufbau auf Anforderung |
| **Sicherungen & Papierkorb** | Inhalts-Snapshots per Klick (+ `.md.ini`), Wiederherstellung mit automatischer Sicherheitskopie davor, ZIP-Download, Liste für das Aufräumen, Wiederherstellung gelöschter Dateien |
| **Revisionen** | Speicherhistorie pro Dokument (neueste `REVISIONS_MAX`, Standard 20), schreibgeschützte Vorschau, Wiederherstellung, die den aktuellen Inhalt als Revision `(pre-restore)` behält |
| **Updates** | Prüfung auf GitHub-Releases (15-Minuten-Cache), Release-Notes, **geprüfte Übernahme** (Tarball + sha256-Manifest je Datei, Sicherung zuerst), Dokumentation der Maschinen-API |
| **Einstellungen** | Jeder `.md.ini`-Schlüssel als typisiertes Formular (schemagetrieben), Neuerzeugung des API-Schlüssels, Änderung des Admin-Passworts |
| **Eigene Themes** | Markenakzente speichern (Primärfarben für hell + dunkel) — sofort im öffentlichen Katalog wählbar |
| **Logs & Audit** | Tägliche Anwendungslogs + JSON-Audit-Trail (wer, was, wann, von wo) |
| **Sicherheit & Zustand** | 20+ Prüfungen: PHP-Version/-Erweiterungen, Schreibbarkeit, Berechtigungen, Stand der Geheimnisse, OPcache, CSP; Härtungs-Checkliste |
| **Werkzeuge** | **Prüfung auf defekte Links / tote Anker / fehlende Medien / verwaiste Dokumente**, Neuaufbau von Backlink- und Tag-Index, **statischer Site-Export** (ZIP aus eigenständigem HTML), Leeren des Caches, Verknüpfungen zu Feed & Sitemap |

### Zero-CDN-Richtlinie

Jedes Asset wird lokal ausgeliefert: die Tailwind-Ausgabe (zur Entwicklungszeit gebaut,
eingecheckt), das mitgelieferte `mermaid.min.js`, KaTeX (CSS+Fonts+mhchem), woff2-Subsets von
Inter & JetBrains Mono (latin/latin-ext/cyrillic), Lucide-Icons, zu einer PHP-Sprite-Map
kompiliert, und die vollständige gemoji-Datenbank. Die Content-Security-Policy erzwingt das:
`default-src 'self'`.

---

## Konfiguration — `.md.ini`

Wird beim ersten Aufruf automatisch neben dem Projektstamm angelegt. Schlüssel auf
Installationsebene werden bei jedem Laden mit sicheren Standardwerten ergänzt — Aktualisierungen
erfordern nie manuelle Eingriffe. Schlüssel für Rendering-Funktionen werden bewusst **nicht**
geschrieben: ihre Standardwerte stehen in `config/defaults.php`, und Besucher können sie pro
Browser umschalten (First-Party-Cookies). Ein Funktionsschlüssel in `.md.ini` **fixiert** die
Einstellung.

```ini
; General
SITE_NAME = MD.Scribe
HOME_DOC  = index.md
BROWSE_DIR =                     ; empty = bundled content/

; Security & admin
ADMIN_ENABLED = true
ADMIN_USERNAME = admin
ADMIN_PASSWORD =                 ; one-shot: hashed on next load, then cleared
ADMIN_PASSWORD_HASH =            ; managed by the panel — don't edit
ADMIN_RATE_LIMIT = 10            ; failed logins / 15 min / IP
API_KEY = mdv_…                  ; machine API credential (auto-generated)
ALLOW_UPDATE = false
ALLOW_RESTORE = false
ALLOW_RAW_HTML = false           ; escape author HTML (recommended)
ALLOW_IFRAMES = false
ALLOW_EXTERNAL_IMAGES = true
SECURITY_HEADERS = true
SECURITY_CSP = true

; Content
DISABLE_UPLOAD = true
DISABLE_CLIPBOARD = false
DISABLE_SAVE_CLIPBOARD_TO_FILE = true
MAX_UPLOAD_MB = 8

; Performance / feeds / appearance / diagnostics — see docs/configuration.md
```

Vollständige Schlüsselreferenz (jeder Schlüssel, Typ, Standardwert, Wirkung):
**[docs/configuration.md](docs/configuration.md)**.
Die Schichtung von `.md.ini` pro Verzeichnis (der `DISABLE_*`-Mechanismus aus v2) bleibt
erhalten: **[docs/configuration.md#per-directory](docs/configuration.md#per-directory-mdini)**.

---

## URL-Vertrag

| URL | Zweck |
| --- | --- |
| `index.php?file=docs/api.md` | ein Dokument rendern |
| `index.php?file=docs/api.md&download=1` | Download des Quelltexts (octet-stream + nosniff) |
| `index.php?media=docs/img/pic.png` | Inhalts-Asset (Bilder inline; SVG/ZIP als Anhang) |
| `index.php?page=browser[&dir=docs]` | Bibliothek / Dateibrowser |
| `index.php?page=search&q=…` | Volltextsuche |
| `index.php?page=themes` / `page=settings` | Theme-Katalog / Einstellungen |
| `index.php?page=sitemap` / `page=feed` | sitemap.xml / RSS |
| `index.php?api=search\|suggest\|toc\|config` | JSON-GET-Endpunkte |
| `index.php?api=render\|save\|upload` | JSON-POST-Endpunkte (CSRF) |
| `admin.php?page=…` / `admin.php?action=…` | Admin-UI / Admin-JSON-API |
| `md.php?file=…` | **v2-kompatibler** Viewer-Shim |
| `updater.php?update=true` · `?restore=latest` · `?api_key=…&action=…&format=json` | **v2-kompatibler** Updater + Maschinen-API |

HTTP-Caching: Dokumente und Medien antworten mit `ETag`/`Last-Modified` und unterstützen
`304 Not Modified`; gerendertes HTML wird zusätzlich serverseitig gecacht, adressiert über
Quelltext-Hash × Funktions-Fingerprint.

---

## Einbetten und CLI-Rendering

Die Markdown-Pipeline ist auch ohne Viewer nutzbar: eine README, eine Release-Notiz oder ein
CMS-Block lässt sich aus jedem PHP-Aufrufer oder Shell-Skript rendern, mit derselben
CommonMark/GFM-Unterstützung, denselben Erweiterungen, demselben Highlighting und Sanitizer.

```php
require '/path/to/MD.Scribe/src/bootstrap.php';

$renderer = new \MdViewer\Standalone\Renderer();
$result = $renderer->renderFile('/srv/repos/ragraf/README.md');
echo $result->html;                 // embed mode: portable code blocks, no numbering
```

```bash
bin/md-scribe-render README.md --html \
  --base-url="https://ragraf.example.com/" \
  --css="https://example.com/readme.css" > readme.html
```

Der Einbettungsmodus erzeugt `<pre><code class="hljs language-…">` und schlichte Überschriften,
sodass ein Stylesheet für GitHub-READMEs (`github-markdown-css` + ein highlight.js-Theme) die
Seite gestalten kann. Vollständige Optionsreferenz und Integrationsrezepte:
**[docs/standalone-renderer.md](docs/standalone-renderer.md)**.

Seit 3.2.2 ist dieselbe Pipeline außerdem ein zustandsloser HTTP-Endpunkt für andere
Anwendungen — Markdown hinein, HTML-Fragment heraus, schlüsselauthentifiziert, ohne Session:

```bash
curl -H "X-Api-Key: $API_KEY" -H 'Content-Type: application/json' \
  -d '{"content": "# Title\n\n- one\n- two", "format": "auto"}' \
  https://docs.example.com/index.php?api=render
```

`format` akzeptiert `auto` (Standard), `markdown`, `html` (bereinigt) und `text`
(unveränderter Text, eingebettet in `<div class="md-text">`). Vertrag, Grenzen und Statuscodes: **[docs/http-api.md](docs/http-api.md)**.

---

## Sicherheit

Verteidigung in der Tiefe — Details und die Härtungs-Checkliste:
**[docs/security.md](docs/security.md)**.

- **Pfadvalidierung**: 14 Ebenen (Länge, NUL, Steuerzeichen, erneute Prüfung nach URL-Decode,
  absolute Pfade, Traversal, strenge Unicode-Whitelist `\p{L}\p{M}\p{Pd}`, Tiefe, Erweiterung,
  realpath-Eingrenzung, symlink-sichere Nachprüfung) — ein Validator für Viewer, Downloads,
  Medien, Uploads, die ini pro Verzeichnis und den Adminbereich.
- **Ausgabebereinigung**: jedes gerenderte Dokument durchläuft einen Sanitizer mit
  DOM-Allowlist (Event-Handler, gefährliche Schemata, Formulare, foreignObject/`<use>` werden
  alle entsorgt); rohes HTML von Autoren wird maskiert, sofern `ALLOW_RAW_HTML` nicht aktiv ist —
  und auch dann bereinigt.
- **Transport**: strikte CSP mit Nonces pro Anfrage, nosniff, Frame-/Referrer-/Permissions-Policies,
  COOP; gehärtete Session-Cookies.
- **Admin**: Anmeldung über password_hash, Ratenbegrenzung pro IP, CSRF bei jeder Änderung,
  Regeneration der Session, Audit-Trail, Soft-Delete, Erstkonfiguration nur von localhost oder
  mit Einmalcode.
- **Geheimnisse**: `.md.ini` wird nie ausgeliefert (`.htaccess` + Layouts außerhalb des
  Docroots); die Maschinen-API antwortet ausschließlich mit JSON.

Sicherheitslücke melden: **[SECURITY.md](SECURITY.md)**.

---

## In Zahlen

Gemessen am 2026-11-14 gegen den `main`-Baum von 3.6.0:

| Bereich | Wert | Messquelle |
| --- | ---: | --- |
| PHP-Quelldateien | 116 | `Get-ChildItem src,public,templates -Recurse -Filter *.php` |
| Testdateien | 22 | `Get-ChildItem tests -Recurse -Filter *.php` |
| PHPUnit-Tests | 214 Tests, 678 Assertions, 1 übersprungen | `composer test` |
| Dokumentationsseiten | 12 | `Get-ChildItem docs -Filter *.md` |
| Composer-Pakete zur Laufzeit | 2 (`league/commonmark`, `scrivo/highlight.php`) | `composer.json` → `require` |
| Kuratierte Themes | 12 | `config/themes.php` |
| CDN-Anfragen zur Laufzeit | 0 | `default-src 'self'` in der erzwungenen CSP |

---

## Entwicklung

```bash
composer install                 # dev deps: phpunit, phpstan
cd tools/frontend && npm install && node build.mjs && cd ../..

composer test                    # 214 PHPUnit tests, 678 assertions
composer stan                    # PHPStan level 6
php -S localhost:8080 -t public public/router.php
```

Die Frontend-Pipeline (Tailwind v4 → `app.css`, esbuild → `app.js`/`admin.js`, Theme-Compiler →
`themes.css`, Icon-/Emoji-Generatoren) läuft nur zur **Entwicklungszeit** — die gebauten Assets
sind eingecheckt, Produktivserver brauchen also kein Node.js. Architektur, Modulkarte,
Release-Checkliste: **[docs/development.md](docs/development.md)**.

### Projektstruktur

```text
MD.Scribe/
├── public/              ← recommended docroot
│   ├── index.php        front controller (viewer, library, pages, JSON API)
│   ├── admin.php        admin panel + admin JSON API
│   ├── router.php       php -S dev router
│   └── assets/          css/ js/ vendor/{mermaid,katex,fonts} img/  (all local)
├── src/                 PSR-4 app code (MdViewer\)
│   ├── Core/            App, Config, FeatureFlags, Request/Response, Session,
│   │                    Csrf, Logger, AuditLog, HttpClient, Paths, Version
│   ├── Http/            FrontController, AdminKernel, Controllers/
│   ├── Markdown/        engine, sanitizer, glossary, mermaid repair, cache,
│   │                    Extensions/ (math, sub/sup, emoji, alerts, renderers)
│   ├── Files/           PathValidator, repository, search index, uploader,
│   │                    FileManager
│   ├── Admin/           auth, backups, updater, health, link checker
│   ├── Theme/           theme catalog (shadcn token model)
│   └── View/            template engine, generated icon sprite
├── templates/           layout/ viewer/ browser/ pages/ admin/
├── config/              defaults.php (schema) · themes.php · manifest.json
├── content/             ← your documents live here (default BROWSE_DIR)
├── storage/             cache/ backups/ logs/ index/ state/ themes/  (writable)
├── docs/                deep documentation
├── tests/               PHPUnit (Unit + Feature)
├── tools/               build pipeline (frontend/, build-manifest.php)
├── deploy/              nginx, Caddy, Docker, php.ini
├── md.php               v2-compatible viewer shim
├── updater.php          v2-compatible updater + machine API shim
└── index.php            project-root entry (docroot-at-root installs)
```

---

## Dokumentation

Zwei Ebenen: das **[Wiki](https://github.com/paulmann/MD.Scribe/wiki)** ist die aufgabenorientierte
Anleitung (in Betrieb nehmen, konfigurieren, betreiben), die Dateien in `docs/` sind die
Referenz, die gegen den laufenden Tag geprüft wird.

| Dokument | Inhalt |
| --- | --- |
| **[Wiki](https://github.com/paulmann/MD.Scribe/wiki)** | Erste Schritte, Installation, Konfiguration, Markdown-Funktionen, Suche, Admin-Leitfaden, HTTP-API, Updates, Fehlersuche, Entwicklung, Changelog — Beginn bei [Startseite](https://github.com/paulmann/MD.Scribe/wiki/Home) |
| [docs/installation.md](docs/installation.md) | Alle Deployment-Layouts, Apache/nginx/Caddy/Docker, Shared Hosting, Berechtigungen |
| [docs/configuration.md](docs/configuration.md) | Vollständige `.md.ini`-Referenz, Dateien pro Verzeichnis, Funktionsschalter |
| [docs/markdown-features.md](docs/markdown-features.md) | Vollständige Syntaxmatrix mit Beispielen |
| [docs/admin-guide.md](docs/admin-guide.md) | Jede Admin-Seite, Arbeitsabläufe, Maschinen-API |
| [docs/theming.md](docs/theming.md) | Theme-System, Tokens, eigene Themes, Rezepte |
| [docs/security.md](docs/security.md) | Bedrohungsmodell, Härtungs-Checkliste, Hinweise zur CSP |
| [docs/http-api.md](docs/http-api.md) | JSON-Endpunkte (öffentlich + Admin + Maschinen-API) |
| [docs/standalone-renderer.md](docs/standalone-renderer.md) | Einbetten der Markdown-Pipeline, CLI- und HTTP-Render-API |
| [docs/development.md](docs/development.md) | Architektur, Build-Pipeline, Tests, Releases |
| [docs/troubleshooting.md](docs/troubleshooting.md) | Häufige Probleme und Lösungen |
| [docs/upgrade-from-v2.md](docs/upgrade-from-v2.md) | Migrationszuordnung und Schritte |
| [docs/AGENTS-context.md](docs/AGENTS-context.md) | Ausführlicher Projektkontext für Coding-Agenten |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Wie eine Änderung vorgeschlagen wird und was zuerst bestehen muss |
| [SECURITY.md](SECURITY.md) | Unterstützte Versionen und vertrauliche Meldung von Sicherheitslücken |
| [CHANGELOG.md](CHANGELOG.md) | Jedes Release, Format Keep a Changelog |

---

## Sprachen der Dokumentation

Die englische README ist maßgeblich. Eine übersetzte README spiegelt diese Datei Abschnitt für
Abschnitt und wird im selben Commit aktualisiert, sobald sich eine Überschrift, ein Befehl oder
eine Zahl ändert. Wiki und `docs/` bleiben englisch.

| Sprache | Datei | Hinweise |
| --- | --- | --- |
| Englisch | [README.md](README.md) | Maßgeblich |
| Russisch | [README.ru.md](README.ru.md) | Übersetzung dieser Datei |
| Deutsch | [README.de.md](README.de.md) | Übersetzung dieser Datei |
| Chinesisch (vereinfacht) | [README.zh.md](README.zh.md) | Übersetzung dieser Datei |
| Hindi | [README.hi.md](README.hi.md) | Übersetzung dieser Datei |
| Spanisch | [README.es.md](README.es.md) | Übersetzung dieser Datei |
| Französisch | [README.fr.md](README.fr.md) | Übersetzung dieser Datei |

Eine Übersetzung, die zurückbleibt, ist ein Fehler: mit Sprache und Abschnitt melden oder einen
Pull Request öffnen, der das behebt.

---

## Aktualisierung von v2

Direkter Austausch: v3 neben den v2-Dateien ausrollen, die Dokumente verschieben (oder
`BROWSE_DIR` darauf zeigen lassen), den `md.php`-Shim im Stamm behalten — jedes alte Lesezeichen
und jeder `?file=`-Link funktioniert. Schritt für Schritt:
**[docs/upgrade-from-v2.md](docs/upgrade-from-v2.md)**.

---

## Mitwirken

Eine logische Änderung pro Pull Request, ein Test für neues Verhalten und keine neue
Laufzeitabhängigkeit ohne vorherige Diskussion. Die Gates sind `composer test` (214 Tests) und
`composer stan` (PHPStan Level 6); beide müssen `0` liefern. Konventionen, Commit-Stil, die
Sprachregel für Dokumentation und die Checkliste für Fehlerberichte:
**[CONTRIBUTING.md](CONTRIBUTING.md)**. Dieses Projekt folgt dem
[Contributor Covenant 2.1](CODE_OF_CONDUCT.md); Hilfe bei einer Frage oder einem Triage wird in
[SUPPORT.md](SUPPORT.md) beschrieben.

## Lizenz

MIT — siehe [LICENSE](LICENSE). Frei für kommerzielle und private Nutzung, Änderung und
Weitergabe, solange der Copyright-Hinweis erhalten bleibt. Frühere Versionen (bis 3.3.0) standen
unter PolyForm Noncommercial 1.0.0.
Markdown-Engine: [league/commonmark](https://commonmark.thephpleague.com/) (BSD-3).
Highlighting: [scrivo/highlight.php](https://github.com/scrivo/highlight.php) (BSD-3, Port von highlight.js).
Diagramme: [Mermaid](https://mermaid.js.org/) (MIT). Formeln: [KaTeX](https://katex.org/) (MIT).
Icons: [Lucide](https://lucide.dev/) (ISC). Emoji-Daten: [gemoji](https://github.com/github/gemoji) (MIT).
Schriften: Inter & JetBrains Mono (SIL OFL).

## Dem Projekt helfen: Belege statt Interaktion

Was tatsächlich hilft, nach Wert geordnet:

- ein **Kompatibilitätsbericht**: PHP-Version (`php -v`), Webserver und Version, Deployment-Layout
  (Docroot `public/`, Docroot im Stamm, Shared Hosting) und der genaue Befehl mit seiner Ausgabe —
  ein Bericht pro eindeutiger Kombination, nachdem die vorhandenen Issues durchsucht wurden;
- eine **Dokumentationskorrektur** als Pull Request, der Datei und Zeile nennt;
- eine **bereinigte Fallstudie**, veröffentlicht mit Erlaubnis des Dateninhabers;
- ein **Hinweis zu Abhängigkeiten oder Sicherheit** für einen Fork oder Wrapper, an derselben
  Stelle offengelegt.

Sterne, Forks, Watcher, Kommentare, Issues, Pull Requests oder Massennachrichten an den
Maintainer oder an Dritte niemals automatisieren. Ein Agent darf einen Bericht entwerfen; ein
Mensch veröffentlicht ihn nach Prüfung.
