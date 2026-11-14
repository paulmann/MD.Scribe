<!--
 * MD.Scribe — Documentation
 * Version: 3.6.0
-->

# MD.Scribe 3

MD.Scribe es una plataforma de documentación Markdown autoalojada en PHP 8.5: un visor completo
de CommonMark/GFM con búsqueda de texto completo, paleta de comandos, catálogo de temas,
diagramas Mermaid y matemáticas KaTeX, además de un panel de administración con gestor de
contenido, editor, copias de seguridad, actualizador automático verificado y registro de
auditoría. No necesita base de datos, ni framework, ni paso de compilación en el servidor, y
todos los recursos se distribuyen localmente, por lo que toda la aplicación funciona sin conexión.

<!-- md-i18n:switcher -->
**En otros idiomas:** [English](README.md) · [Deutsch](README.de.md) · Español · [Français](README.fr.md) · [हिन्दी](README.hi.md) · [中文](README.zh.md) · [Русский](README.ru.md)

[![CI](https://github.com/paulmann/MD.Scribe/actions/workflows/ci.yml/badge.svg)](https://github.com/paulmann/MD.Scribe/actions/workflows/ci.yml)
[![Versión](https://img.shields.io/github/v/release/paulmann/MD.Scribe?label=release&sort=semver)](https://github.com/paulmann/MD.Scribe/releases)
[![Licencia: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://php.net)
[![Pruebas](https://img.shields.io/badge/tests-220%20passed-brightgreen)](tests)
[![Peticiones CDN](https://img.shields.io/badge/CDN%20requests-0-success)](#política-de-cero-cdn)

<img src="https://github.com/paulmann/MD.Scribe/raw/main/assets/social-preview.png"
     alt="MD.Scribe: plataforma de documentación Markdown autoalojada en PHP 8.5 con visor y panel de administración"
     width="100%">

[**Inicio rápido**](#inicio-rápido) · [**Recorrido por las funciones**](#recorrido-por-las-funciones) · [**Configuración**](#configuración--mdini) · [**Documentación**](#documentación) · [**Wiki**](https://github.com/paulmann/MD.Scribe/wiki) · [**AGENTS.md**](AGENTS.md) · [**Changelog**](CHANGELOG.md) · [**Seguridad**](SECURITY.md) · [**Contribuir**](CONTRIBUTING.md)

> **Estado de la documentación.** La versión y todas las cifras de este README se midieron el
> 2026-11-14 sobre el árbol `main` de la 3.6.0, con el comando indicado allí donde aparece la
> cifra. Las guías orientadas a tareas están en la
> [wiki](https://github.com/paulmann/MD.Scribe/wiki/Home); la documentación de referencia está
> en [`docs/`](docs/installation.md).

## Contenido

- [¿Qué es MD.Scribe?](#qué-es-mdscribe)
- [Inicio rápido](#inicio-rápido)
- [Recorrido por las funciones](#recorrido-por-las-funciones)
- [Configuración — `.md.ini`](#configuración--mdini)
- [Contrato de URL](#contrato-de-url)
- [Integración y renderizado por CLI](#integración-y-renderizado-por-cli)
- [Seguridad](#seguridad)
- [En cifras](#en-cifras)
- [Desarrollo](#desarrollo)
- [Documentación](#documentación)
- [Idiomas de la documentación](#idiomas-de-la-documentación)
- [Actualización desde la v2](#actualización-desde-la-v2)
- [Contribuir](#contribuir)
- [Licencia](#licencia)
- [Apoyar el proyecto: pruebas, no interacción](#apoyar-el-proyecto-pruebas-no-interacción)

---

## ¿Qué es MD.Scribe?

Basta con apuntarlo a una carpeta de archivos `.md` para tener un sitio de documentación:

- **`public/index.php`** renderiza un documento (`?file=path/doc.md`), una **biblioteca** con
  búsqueda cuando no se solicita ningún archivo, además de las páginas **Temas**, **Ajustes** y
  **Búsqueda**, `sitemap.xml` y un canal RSS.
- **`public/admin.php`** es el panel de administración: panel de control, gestor de archivos,
  editor de panel dividido con vista previa en vivo, subidas, control del índice de búsqueda,
  copias de seguridad y papelera, actualizador automático, editor de ajustes, registros, registro
  de auditoría, comprobaciones de seguridad y estado, y herramientas (comprobador de enlaces
  rotos, control de caché).
- **`md.php` / `updater.php`** en la raíz del proyecto son capas de compatibilidad: todo el
  contrato de URL de la v2 (`?file=`, `&download=1`, `?update=true`, `?restore=latest`, la API
  para máquinas) sigue funcionando tras una actualización.

**¿Es nuevo aquí?** Empiece por la
[wiki](https://github.com/paulmann/MD.Scribe/wiki/Getting-Started)
([Inicio](https://github.com/paulmann/MD.Scribe/wiki/Home)): le lleva desde el archivo de la
versión hasta una instalación verificada en cuatro comandos.

Todo el estilo procede de un **sistema de diseño con tokens de shadcn/ui sobre una compilación
local de Tailwind CSS v4**, con modos claro/oscuro/sistema, 12 temas seleccionados y un mezclador
neutro × acento. Mermaid, KaTeX, Inter, JetBrains Mono y todos los iconos se distribuyen
incluidos: la aplicación funciona por completo **sin conexión / en red aislada**.

---

## Inicio rápido

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

Despliegues en producción (docroot en `public/`, docroot en la raíz del proyecto, Apache, nginx,
Caddy, Docker, alojamiento compartido): **[docs/installation.md](docs/installation.md)**.

Requisitos: **PHP ≥ 8.5** con `mbstring`, `dom`, `libxml`, `json` (`curl` + `phar` + `zip`
recomendados para el actualizador y los archivos de copia de seguridad).

---

## Recorrido por las funciones

### Experiencia de lectura

| Área | Funciones |
| --- | --- |
| Tipografía | Escala tipográfica fluida, controles de tamaño de texto, ancho de página e interlineado, opciones de fuente serif y de sistema, hoja de estilo para impresión |
| Navegación | TOC automático con scrollspy, numeración de encabezados + anclas al pasar el cursor, marcador `[TOC]`, migas de pan, paginador anterior/siguiente, barra lateral de carpetas, enlaces de salto |
| Descubrimiento | **Búsqueda de texto completo** en el servidor (índice invertido, compatible con CJK) con **operadores** (`tag:`, `path:`, valores entre comillas), **paleta de comandos Ctrl+K** (documentos, encabezados, acciones), **índice de etiquetas** (`?page=tags`), sitemap.xml, canal RSS |
| Contenido enriquecido | **Resaltado de sintaxis** en el servidor (~190 lenguajes), diagramas **Mermaid** con reparación automática + diagnóstico, matemáticas **KaTeX** (incl. mhchem), **alertas** al estilo de GitHub, listas de tareas, notas al pie, listas de definiciones, `==marcas==`, ^superíndice^/~subíndice~, más de 1900 códigos cortos de emoji, **lightbox** de imágenes con pies de foto, estimaciones de tiempo de lectura |
| Base de conocimiento | **Wikilinks** (`[[Página]]`, `[[Página\|Alias]]`, `[[Página#Encabezado]]`) con marcadores de página inexistente, **backlinks** («Enlazado desde»), **etiquetas** de frontmatter con un índice público, **historial de revisiones** por documento con restauración, **exportación de sitio estático** a un ZIP autocontenido |
| Glosario | Cualquier tabla de dos columnas se convierte en **tooltips** al pasar el cursor o tocar, con expansión de alias (`Término (ABBR)`), contenido DOM en vivo: cero duplicación |
| Sin conexión | **PWA** instalable: manifiesto web + service worker — recursos con caché primero, documentos con red primero y la última copia leída como respaldo, las API JSON nunca se almacenan en caché (`PWA_ENABLED`) |
| Temas | 12 preajustes + mezclador de neutro × acento × radio de borde × fuente, claro/oscuro/**detección automática del sistema**, persistencia por visitante, **temas personalizados** gestionados desde la administración, inicialización previa al pintado sin FOUC |

### Compatibilidad con Markdown

CommonMark **0.31 completo** mediante `league/commonmark`, además de GFM (tablas con alineación,
tachado, listas de tareas, enlaces automáticos), notas al pie (de referencia **y** en línea),
listas de definiciones, sintaxis de atributos `{#id .class}`, puntuación tipográfica inteligente
(opcional), frontmatter YAML (`title`, `description`, `tags`, `author`, `date`, `draft`, `toc`,
`numbering`), reescritura de enlaces relativos `.md` con marcadores de enlace muerto,
`[[wikilinks]]` con resolución de alias/ancla, y extras heredados de MD.Viewer (cadenas de
patrones universales, tablas de glosario, regla de nombres `md.md`). Matriz completa:
**[docs/markdown-features.md](docs/markdown-features.md)**.

### Panel de administración

| Página | Qué hace |
| --- | --- |
| **Panel de control** | Estadísticas del corpus, frescura del índice, resumen de estado, acciones rápidas, documentos recientes, flujo de actividad |
| **Archivos** | Árbol de todo el contenido, filtro, crear/renombrar/mover/eliminar (borrado lógico a la **papelera**), carpetas, subida múltiple (.md + imágenes con detección de contenido), **nuevo desde plantilla** |
| **Editor** | Vista previa en vivo en panel dividido (el motor *real*), **barra de herramientas Markdown** (negrita, cursiva, código, enlace, wikilink, lista, alerta, tabla), guardado con ⌘S y **detección de conflictos por sha256**, contador de palabras y de tiempo de lectura, subida de imágenes en el cursor, enlaces directos `?page=editor&file=…` |
| **Índice de búsqueda** | Estadísticas, frescura, reconstrucción a demanda |
| **Copias de seguridad y papelera** | Instantáneas del contenido con un clic (+ `.md.ini`), restauración con copia de seguridad automática previa, descarga en zip, listado listo para purgar, restauración de archivos eliminados |
| **Revisiones** | Historial de guardado por documento (las `REVISIONS_MAX` más recientes, 20 por defecto), vista previa de solo lectura, restauración que conserva el contenido actual como revisión `(pre-restore)` |
| **Actualizaciones** | Comprobación de versiones de GitHub (caché de 15 min), notas de la versión, **aplicación verificada** (tarball + manifiesto sha256 por archivo, copia de seguridad primero), documentación de la API para máquinas |
| **Ajustes** | Cada clave de `.md.ini` como formulario tipado (guiado por esquema), regeneración de la clave de API, cambio de la contraseña de administración |
| **Temas personalizados** | Guarde acentos de marca (primarios claro+oscuro), seleccionables al instante en el catálogo público |
| **Registros y auditoría** | Registros diarios de la aplicación + registro de auditoría JSON (quién hizo qué, cuándo y desde dónde) |
| **Seguridad y estado** | Más de 20 comprobaciones: versión y extensiones de PHP, permisos de escritura, permisos, situación de los secretos, OPcache, CSP; lista de endurecimiento |
| **Herramientas** | **Comprobador de enlaces rotos / anclas muertas / medios ausentes / documentos huérfanos**, reconstrucción de los índices de backlinks y etiquetas, **exportación de sitio estático** (ZIP de HTML autocontenido), limpieza de caché, atajos al feed y al sitemap |

### Política de cero CDN

Todos los recursos se distribuyen localmente: la salida de Tailwind (compilada en tiempo de
desarrollo y versionada), `mermaid.min.js` incluido, KaTeX (CSS+fuentes+mhchem), subconjuntos
woff2 de Inter y JetBrains Mono (latin/latin-ext/cyrillic), iconos de Lucide compilados en un
mapa de sprites PHP y la base de datos completa de gemoji. La Content-Security-Policy lo impone:
`default-src 'self'`.

---

## Configuración — `.md.ini`

Se crea automáticamente junto a la raíz del proyecto en la primera carga. Las claves de nivel de
instalación se añaden con valores predeterminados seguros en cada carga: las actualizaciones
nunca requieren ediciones manuales. Las claves de funciones de renderizado deliberadamente **no**
se escriben: sus valores predeterminados viven en `config/defaults.php` y los visitantes pueden
alternarlas por navegador (cookies propias). Añadir una clave de función a `.md.ini` la
**bloquea de forma fija**.

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

Referencia completa de claves (cada clave, tipo, valor predeterminado y efecto):
**[docs/configuration.md](docs/configuration.md)**.
Se conserva la estratificación de `.md.ini` por directorio (el mecanismo `DISABLE_*` de la v2):
**[docs/configuration.md#per-directory](docs/configuration.md#per-directory-mdini)**.

---

## Contrato de URL

| URL | Propósito |
| --- | --- |
| `index.php?file=docs/api.md` | renderizar un documento |
| `index.php?file=docs/api.md&download=1` | descarga del origen (octet-stream + nosniff) |
| `index.php?media=docs/img/pic.png` | recurso de contenido (imágenes en línea; SVG/zip como adjuntos) |
| `index.php?page=browser[&dir=docs]` | biblioteca / explorador de archivos |
| `index.php?page=search&q=…` | búsqueda de texto completo |
| `index.php?page=themes` / `page=settings` | catálogo de temas / ajustes |
| `index.php?page=sitemap` / `page=feed` | sitemap.xml / RSS |
| `index.php?api=search\|suggest\|toc\|config` | endpoints GET JSON |
| `index.php?api=render\|save\|upload` | endpoints POST JSON (CSRF) |
| `admin.php?page=…` / `admin.php?action=…` | interfaz de administración / API JSON de administración |
| `md.php?file=…` | capa de compatibilidad del visor, **compatible con la v2** |
| `updater.php?update=true` · `?restore=latest` · `?api_key=…&action=…&format=json` | actualizador **compatible con la v2** + API para máquinas |

Caché HTTP: los documentos y los medios responden con `ETag`/`Last-Modified` y admiten
`304 Not Modified`; el HTML renderizado se almacena además en caché en el servidor, indexado por
el hash del origen × la huella de funcionalidad.

---

## Integración y renderizado por CLI

El procesamiento de Markdown se puede usar sin el visor: renderice un README, una nota de versión
o un bloque de CMS desde cualquier código PHP o script de shell, con la misma compatibilidad con
CommonMark/GFM, las mismas extensiones, el mismo resaltado y el mismo saneador.

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

El modo de integración emite `<pre><code class="hljs language-…">` y encabezados simples, de modo
que una hoja de estilo para README de GitHub (`github-markdown-css` + un tema de highlight.js)
pueda dar estilo a la página. Referencia completa de opciones y recetas de integración:
**[docs/standalone-renderer.md](docs/standalone-renderer.md)**.

Desde la 3.2.2, ese mismo procesamiento es también un endpoint HTTP sin estado para otras
aplicaciones: entra Markdown, sale un fragmento HTML, autenticado por clave y sin sesión:

```bash
curl -H "X-Api-Key: $API_KEY" -H 'Content-Type: application/json' \
  -d '{"content": "# Title\n\n- one\n- two", "format": "auto"}' \
  https://docs.example.com/index.php?api=render
```

`format` acepta `auto` (predeterminado), `markdown`, `html` (saneado) y `text` (texto literal
envuelto en `<div class="md-text">`). Contrato, límites y códigos de estado:
**[docs/http-api.md](docs/http-api.md)**.

---

## Seguridad

Defensa en profundidad: detalles y lista de endurecimiento:
**[docs/security.md](docs/security.md)**.

- **Validación de rutas**: 14 capas (longitud, NUL, caracteres de control, nueva comprobación
  tras decodificar la URL, rutas absolutas, path traversal, lista blanca estricta de Unicode
  `\p{L}\p{M}\p{Pd}`, profundidad, extensión, contención por realpath, nueva comprobación segura
  frente a enlaces simbólicos): un único validador para el visor, las descargas, los medios, las
  subidas, el ini por directorio y la administración.
- **Saneamiento de la salida**: todo documento renderizado pasa por un saneador con lista blanca
  de DOM (los manejadores de eventos, los esquemas peligrosos, los formularios y
  foreignObject/`<use>` se eliminan); el HTML sin procesar del autor se escapa salvo que
  `ALLOW_RAW_HTML` esté activo, y aun entonces se sanea.
- **Transporte**: CSP estricta con nonces por petición, nosniff, políticas de
  frame/referrer/permisos, COOP; cookies de sesión endurecidas.
- **Administración**: inicio de sesión con password_hash, limitación de tasa por IP, CSRF en cada
  mutación, regeneración de sesión, registro de auditoría, borrados lógicos, configuración
  inicial restringida a localhost o a un código de un solo uso.
- **Secretos**: `.md.ini` nunca se sirve (`.htaccess` + diseños fuera del docroot); la API para
  máquinas responde únicamente JSON.

Para informar de una vulnerabilidad: **[SECURITY.md](SECURITY.md)**.

---

## En cifras

Medido el 2026-11-14 sobre el árbol `main` de la 3.6.0:

| Superficie | Valor | Fuente de la medición |
| --- | ---: | --- |
| Archivos fuente PHP | 116 | `Get-ChildItem src,public,templates -Recurse -Filter *.php` |
| Archivos de prueba | 22 | `Get-ChildItem tests -Recurse -Filter *.php` |
| Pruebas PHPUnit | 214 pruebas, 678 aserciones, 1 omitida | `composer test` |
| Páginas de documentación | 12 | `Get-ChildItem docs -Filter *.md` |
| Paquetes de Composer en tiempo de ejecución | 2 (`league/commonmark`, `scrivo/highlight.php`) | `composer.json` → `require` |
| Temas seleccionados | 12 | `config/themes.php` |
| Peticiones CDN en tiempo de ejecución | 0 | `default-src 'self'` en la CSP aplicada |

---

## Desarrollo

```bash
composer install                 # dev deps: phpunit, phpstan
cd tools/frontend && npm install && node build.mjs && cd ../..

composer test                    # 214 PHPUnit tests, 678 assertions
composer stan                    # PHPStan level 6
php -S localhost:8080 -t public public/router.php
```

La canalización del frontend (Tailwind v4 → `app.css`, esbuild → `app.js`/`admin.js`, compilador
de temas → `themes.css`, generadores de iconos y de emoji) se ejecuta solo en tiempo de
**desarrollo**: los recursos compilados se versionan, así que los servidores de producción no
necesitan Node.js. Arquitectura, mapa de módulos y lista de comprobación de la versión:
**[docs/development.md](docs/development.md)**.

### Estructura del proyecto

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

## Documentación

Dos capas: la **[wiki](https://github.com/paulmann/MD.Scribe/wiki)** es la guía orientada a
tareas (ponerlo en marcha, configurarlo, operarlo), mientras que los archivos de `docs/` son la
referencia que se consulta según la etiqueta que se ejecuta.

| Documento | Contenido |
| --- | --- |
| **[Wiki](https://github.com/paulmann/MD.Scribe/wiki)** | Primeros pasos, instalación, configuración, funciones de Markdown, búsqueda, guía de administración, API HTTP, actualizaciones, solución de problemas, desarrollo, changelog — empiece por [Inicio](https://github.com/paulmann/MD.Scribe/wiki/Home) |
| [docs/installation.md](docs/installation.md) | Todos los diseños de despliegue, Apache/nginx/Caddy/Docker, alojamiento compartido, permisos |
| [docs/configuration.md](docs/configuration.md) | Referencia completa de `.md.ini`, archivos por directorio, conmutadores de funciones |
| [docs/markdown-features.md](docs/markdown-features.md) | Matriz completa de sintaxis con ejemplos |
| [docs/admin-guide.md](docs/admin-guide.md) | Cada página de administración, flujos de trabajo, API para máquinas |
| [docs/theming.md](docs/theming.md) | Sistema de temas, tokens, temas personalizados, recetas |
| [docs/security.md](docs/security.md) | Modelo de amenazas, lista de endurecimiento, notas sobre CSP |
| [docs/http-api.md](docs/http-api.md) | Endpoints JSON (públicos + administración + API para máquinas) |
| [docs/standalone-renderer.md](docs/standalone-renderer.md) | Integración del procesamiento de Markdown, CLI y API HTTP de renderizado |
| [docs/development.md](docs/development.md) | Arquitectura, canalización de compilación, pruebas, versiones |
| [docs/troubleshooting.md](docs/troubleshooting.md) | Problemas habituales y soluciones |
| [docs/upgrade-from-v2.md](docs/upgrade-from-v2.md) | Correspondencia de la migración y pasos |
| [docs/AGENTS-context.md](docs/AGENTS-context.md) | Contexto profundo del proyecto para agentes de programación |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Cómo proponer un cambio y qué debe pasar antes |
| [SECURITY.md](SECURITY.md) | Versiones compatibles y notificación privada de vulnerabilidades |
| [CHANGELOG.md](CHANGELOG.md) | Cada versión, formato Keep a Changelog |

---

## Idiomas de la documentación

El README en inglés es el canónico. Un README traducido replica este archivo sección por sección
y se actualiza en el mismo commit siempre que cambie un encabezado, un comando o una cifra. La
wiki y `docs/` siguen en inglés.

| Idioma | Archivo | Notas |
| --- | --- | --- |
| Inglés | [README.md](README.md) | Canónico |
| Ruso | [README.ru.md](README.ru.md) | Traducción de este archivo |
| Alemán | [README.de.md](README.de.md) | Traducción de este archivo |
| Chino (simplificado) | [README.zh.md](README.zh.md) | Traducción de este archivo |
| Hindi | [README.hi.md](README.hi.md) | Traducción de este archivo |
| Español | [README.es.md](README.es.md) | Traducción de este archivo |
| Francés | [README.fr.md](README.fr.md) | Traducción de este archivo |

Una traducción que se queda atrás es un defecto: notifíquelo indicando el idioma y la sección, o
abra un pull request que lo corrija.

---

## Actualización desde la v2

Sustitución directa: despliegue la v3 junto a sus archivos de la v2, mueva sus documentos (o
apunte `BROWSE_DIR` a ellos) y conserve la capa `md.php` de la raíz: todos los marcadores antiguos
y los enlaces `?file=` siguen funcionando. Paso a paso:
**[docs/upgrade-from-v2.md](docs/upgrade-from-v2.md)**.

---

## Contribuir

Un cambio lógico por pull request, una prueba para cada comportamiento nuevo y ninguna dependencia
de ejecución nueva sin hablarlo antes. Las puertas son `composer test` (214 pruebas) y
`composer stan` (PHPStan nivel 6); ambas deben salir con `0`. Convenciones, estilo de commit, la
política de idioma de la documentación y la lista de comprobación para informes de errores:
**[CONTRIBUTING.md](CONTRIBUTING.md)**. Este proyecto sigue el
[Contributor Covenant 2.1](CODE_OF_CONDUCT.md); la ayuda con una pregunta o un triaje se describe
en [SUPPORT.md](SUPPORT.md).

## Licencia

MIT: consulte [LICENSE](LICENSE). Libre para uso comercial y privado, modificación y
redistribución, conservando el aviso de copyright. Las versiones anteriores (hasta la 3.3.0) se
distribuyeron bajo PolyForm Noncommercial 1.0.0.
Motor de Markdown: [league/commonmark](https://commonmark.thephpleague.com/) (BSD-3).
Resaltado: [scrivo/highlight.php](https://github.com/scrivo/highlight.php) (BSD-3, port de
highlight.js).
Diagramas: [Mermaid](https://mermaid.js.org/) (MIT). Matemáticas: [KaTeX](https://katex.org/)
(MIT).
Iconos: [Lucide](https://lucide.dev/) (ISC). Datos de emoji:
[gemoji](https://github.com/github/gemoji) (MIT).
Fuentes: Inter y JetBrains Mono (SIL OFL).

## Apoyar el proyecto: pruebas, no interacción

Lo que realmente ayuda, por orden de valor:

- un **informe de compatibilidad**: versión de PHP (`php -v`), servidor web y versión, diseño del
  despliegue (docroot `public/`, docroot en la raíz, alojamiento compartido) y el comando exacto
  con su salida: un informe por combinación única, después de buscar en las incidencias
  existentes;
- una **corrección de la documentación** como pull request que indique el archivo y la línea;
- un **caso de estudio saneado** publicado con el permiso del propietario de los datos;
- una **nota sobre dependencias o seguridad** para un fork o un wrapper, divulgada en el mismo
  lugar.

Nunca automatice estrellas, forks, watchers, comentarios, incidencias, pull requests ni mensajes
masivos al mantenedor o a terceros. Un agente puede redactar un informe; una persona lo publica
tras revisarlo.
