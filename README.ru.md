<!--
 * MD.Scribe — Documentation
 * Version: 3.6.3
-->

# MD.Scribe 3

MD.Scribe — это самостоятельно размещаемая платформа документации на Markdown для PHP 8.5:
просмотрщик, полностью поддерживающий CommonMark/GFM, с полнотекстовым поиском, командной
палитрой, каталогом тем, диаграммами Mermaid и формулами KaTeX, а также панель
администрирования с менеджером содержимого, редактором, резервными копиями, автообновлением с
проверкой целостности и журналом аудита. База данных, фреймворк и сборка на сервере не нужны, а
все ресурсы поставляются локально, поэтому приложение полностью работает офлайн.

<!-- md-i18n:switcher -->
**На других языках:** [English](README.md) · [Deutsch](README.de.md) · [Español](README.es.md) · [Français](README.fr.md) · [हिन्दी](README.hi.md) · [中文](README.zh.md) · Русский

[![CI](https://github.com/paulmann/MD.Scribe/actions/workflows/ci.yml/badge.svg)](https://github.com/paulmann/MD.Scribe/actions/workflows/ci.yml)
[![Релиз](https://img.shields.io/github/v/release/paulmann/MD.Scribe?label=release&sort=semver)](https://github.com/paulmann/MD.Scribe/releases)
[![Лицензия: MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://php.net)
[![Тесты](https://img.shields.io/badge/tests-241%20passed-brightgreen)](tests)
[![Запросы к CDN](https://img.shields.io/badge/CDN%20requests-0-success)](#политика-отказа-от-cdn)

<img src="https://github.com/paulmann/MD.Scribe/raw/main/assets/social-preview.png"
     alt="MD.Scribe — самостоятельно размещаемая платформа документации на Markdown для PHP 8.5 с просмотрщиком и панелью администрирования"
     width="100%">

[**Быстрый старт**](#быстрый-старт) · [**Обзор возможностей**](#обзор-возможностей) · [**Настройка**](#настройка--mdini) · [**Документация**](#документация) · [**Вики**](https://github.com/paulmann/MD.Scribe/wiki) · [**AGENTS.md**](AGENTS.md) · [**История изменений**](CHANGELOG.md) · [**Безопасность**](SECURITY.md) · [**Участие в проекте**](CONTRIBUTING.md)

> **Состояние документации.** Версия и все числа в этом README измерены 2026-11-14 на дереве
> `main` версии 3.6.3; команда, которой получено число, названа рядом с ним.
> Руководства по задачам находятся в [вики](https://github.com/paulmann/MD.Scribe/wiki/Home);
> справочная документация — в [`docs/`](docs/installation.md).

## Содержание

- [Что такое MD.Scribe?](#что-такое-mdscribe)
- [Быстрый старт](#быстрый-старт)
- [Обзор возможностей](#обзор-возможностей)
- [Настройка — `.md.ini`](#настройка--mdini)
- [Контракт URL](#контракт-url)
- [Встраивание и рендеринг из CLI](#встраивание-и-рендеринг-из-cli)
- [Безопасность](#безопасность)
- [В цифрах](#в-цифрах)
- [Разработка](#разработка)
- [Документация](#документация)
- [Языки документации](#языки-документации)
- [Обновление с версии 2](#обновление-с-версии-2)
- [Участие в проекте](#участие-в-проекте)
- [Лицензия](#лицензия)
- [Помощь проекту: факты, а не активность](#помощь-проекту-факты-а-не-активность)

---

## Что такое MD.Scribe?

Укажите папку с файлами `.md` — и у вас есть сайт документации:

- **`public/index.php`** отрисовывает документ (`?file=path/doc.md`), а если файл не запрошен —
  **библиотеку** с поиском, а также страницы **Темы**, **Настройки** и **Поиск**, `sitemap.xml`
  и ленту RSS.
- **`public/admin.php`** — панель администрирования: сводка, менеджер файлов, редактор с
  разделённым экраном и живым предпросмотром, загрузки, управление поисковым индексом,
  резервные копии и корзина, автообновление, редактор настроек, журналы, журнал аудита,
  проверки безопасности и работоспособности, а также инструменты (проверка битых ссылок,
  управление кешем).
- **`md.php` / `updater.php`** в корне проекта — шимы совместимости: весь контракт URL версии 2
  (`?file=`, `&download=1`, `?update=true`, `?restore=latest`, машинный API) продолжает работать
  после обновления.

**Впервые здесь?** Начните с [вики](https://github.com/paulmann/MD.Scribe/wiki/Getting-Started)
([Главная](https://github.com/paulmann/MD.Scribe/wiki/Home)) — она проведёт вас от архива релиза
до проверенной установки за четыре команды.

Всё оформлено **дизайн-системой на токенах shadcn/ui поверх локальной сборки Tailwind CSS v4**
со светлым, тёмным и системным режимами, 12 отобранными темами и микшером «нейтральный × акцент».
Mermaid, KaTeX, Inter, JetBrains Mono и все иконки поставляются вместе с проектом — приложение
полностью работает **офлайн, в изолированной сети**.

---

## Быстрый старт

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

Промышленное развёртывание (docroot в `public/`, docroot в корне проекта, Apache, nginx, Caddy,
Docker, виртуальный хостинг): **[docs/installation.md](docs/installation.md)**.

Требования: **PHP ≥ 8.5** с расширениями `mbstring`, `dom`, `libxml`, `json` (`curl`, `phar` и
`zip` рекомендуются для автообновления и архивов резервных копий).

---

## Обзор возможностей

### Удобство чтения

| Раздел | Возможности |
| --- | --- |
| Типографика | Плавная шкала кеглей, управление размером текста, шириной страницы и межстрочным интервалом, варианты шрифтов с засечками и системных, таблица стилей для печати |
| Навигация | Автоматическое оглавление со слежением за прокруткой, номера заголовков и якоря при наведении, заполнитель `[TOC]`, хлебные крошки, переходы «назад/вперёд», боковая панель папки, ссылки для пропуска навигации |
| Поиск | Серверный **полнотекстовый поиск** (инвертированный индекс, с учётом CJK) с **операторами** (`tag:`, `path:`, значения в кавычках), **командная палитра Ctrl+K** (документы, заголовки, действия), **индекс тегов** (`?page=tags`), sitemap.xml, лента RSS |
| Насыщенное содержимое | Серверная **подсветка синтаксиса** (~190 языков), диаграммы **Mermaid** с автоисправлением и диагностикой, формулы **KaTeX** (включая mhchem), **оповещения** в стиле GitHub, списки задач, сноски, списки определений, `==marks==`, надстрочные ^super^ и подстрочные ~sub~ символы, 1 900+ сокращений эмодзи, **лайтбокс** изображений с подписями, оценка времени чтения |
| База знаний | **Викиссылки** (`[[Page]]`, `[[Page\|Alias]]`, `[[Page#Heading]]`) с пометками отсутствующих страниц, **обратные ссылки** («Ссылаются на»), **теги** из frontmatter с публичным индексом, **история ревизий** каждого документа с восстановлением, **экспорт статического сайта** в самодостаточный ZIP |
| Глоссарий | Любая таблица из двух столбцов становится **всплывающими подсказками** при наведении и касании, с раскрытием псевдонимов (`Term (ABBR)`), на живом содержимом DOM — без дублирования |
| Офлайн | Устанавливаемое **PWA**: веб-манифест и service worker — ресурсы берутся сначала из кеша, документы запрашиваются из сети с откатом на последнюю прочитанную копию, JSON API никогда не кешируются (`PWA_ENABLED`) |
| Темы | 12 предустановок и микшер «нейтральный × акцент × радиус × шрифт», светлый/тёмный/**автоопределение системы**, сохранение выбора для каждого посетителя, **пользовательские темы** под управлением администратора, инициализация до отрисовки без FOUC |

### Поддержка Markdown

CommonMark **0.31-complete** (полная поддержка) на `league/commonmark`, плюс GFM (таблицы с
выравниванием, зачёркивание, списки задач, автоссылки), сноски (как ссылочные, так и
внутристрочные), списки определений, синтаксис атрибутов `{#id .class}`, умная типографика
(включается по желанию), YAML frontmatter (`title`, `description`, `tags`, `author`, `date`,
`draft`, `toc`, `numbering`), перезапись относительных ссылок `.md` с пометками мёртвых ссылок,
`[[wikilinks]]` с разрешением псевдонимов и якорей, а также унаследованные дополнения MD.Viewer
(универсальные цепочки шаблонов, таблицы глоссария, правило именования `md.md`). Полная матрица:
**[docs/markdown-features.md](docs/markdown-features.md)**.

### Панель администрирования

| Страница | Назначение |
| --- | --- |
| **Сводка** | Статистика корпуса, свежесть индекса, сводка работоспособности, быстрые действия, последние документы, лента активности |
| **Файлы** | Дерево всего содержимого, фильтр, создание/переименование/перемещение/удаление (мягкое удаление в **корзину**), папки, множественная загрузка (.md и изображения с определением типа по содержимому), **создание из шаблона** |
| **Редактор** | Живой предпросмотр в разделённом экране (*настоящий* движок), **панель инструментов Markdown** (жирный, курсив, код, ссылка, викиссылка, список, оповещение, таблица), сохранение по ⌘S с **обнаружением конфликтов по sha256**, счётчик слов и времени чтения, загрузка изображения в позицию курсора, прямые ссылки `?page=editor&file=…` |
| **Поисковый индекс** | Статистика, свежесть, пересборка по запросу |
| **Резервные копии и корзина** | Снимки содержимого в один клик (+ `.md.ini`), восстановление с автоматической страховочной копией перед ним, скачивание в zip, список, готовый к очистке, восстановление удалённых файлов |
| **Ревизии** | История сохранений каждого документа (последние `REVISIONS_MAX`, по умолчанию 20), предпросмотр только для чтения, восстановление, сохраняющее текущее содержимое как ревизию `(pre-restore)` |
| **Обновления** | Проверка релизов GitHub (кеш на 15 минут), примечания к релизу, **проверенное применение** (tarball и манифест sha256 по файлам, сначала резервная копия), документация машинного API |
| **Настройки** | Каждый ключ `.md.ini` в виде типизированной формы (по схеме), перегенерация ключа API, смена пароля администратора |
| **Пользовательские темы** | Сохранение фирменных акцентов (основные цвета для светлой и тёмной темы) — сразу доступны в публичном каталоге |
| **Журналы и аудит** | Ежедневные журналы приложения и журнал аудита в JSON (кто, что, когда и откуда сделал) |
| **Безопасность и работоспособность** | 20+ проверок: версия и расширения PHP, записываемость, права, состояние секретов, OPcache, CSP; контрольный список укрепления |
| **Инструменты** | **Проверка битых ссылок, мёртвых якорей, отсутствующих медиафайлов и осиротевших документов**, пересборка индексов обратных ссылок и тегов, **экспорт статического сайта** (ZIP с самодостаточным HTML), очистка кеша, быстрый доступ к ленте и sitemap |

### Политика отказа от CDN

Все ресурсы поставляются локально: результат сборки Tailwind (собирается на этапе разработки и
коммитится), поставляемый с проектом `mermaid.min.js`, KaTeX (CSS, шрифты и mhchem), подмножества
woff2 Inter и JetBrains Mono (latin/latin-ext/cyrillic), иконки lucide, скомпилированные в карту
спрайтов на PHP, и полная база gemoji. Это закреплено политикой Content-Security-Policy:
`default-src 'self'`.

---

## Настройка — `.md.ini`

Создаётся автоматически рядом с корнем проекта при первой загрузке. Ключи уровня установки
дописываются с безопасными значениями по умолчанию при каждой загрузке — при обновлении ручные
правки не нужны. Ключи возможностей отрисовки намеренно **не** записываются: их значения по
умолчанию находятся в `config/defaults.php`, а посетители могут переключать их в своём браузере
(собственные cookie). Если добавить ключ возможности в `.md.ini`, она **жёстко фиксируется**.

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

Полный справочник ключей (каждый ключ, тип, значение по умолчанию, эффект):
**[docs/configuration.md](docs/configuration.md)**.
Многоуровневость `.md.ini` по каталогам (механизм `DISABLE_*` из версии 2) сохранена:
**[docs/configuration.md#per-directory](docs/configuration.md#per-directory-mdini)**.

---

## Контракт URL

| URL | Назначение |
| --- | --- |
| `index.php?file=docs/api.md` | отрисовка документа |
| `index.php?file=docs/api.md&download=1` | скачивание исходника (octet-stream + nosniff) |
| `index.php?media=docs/img/pic.png` | ресурс содержимого (изображения встроенные; SVG/zip как вложения) |
| `index.php?page=browser[&dir=docs]` | библиотека / обозреватель файлов |
| `index.php?page=search&q=…` | полнотекстовый поиск |
| `index.php?page=themes` / `page=settings` | каталог тем / настройки |
| `index.php?page=sitemap` / `page=feed` | sitemap.xml / RSS |
| `index.php?api=search\|suggest\|toc\|config` | конечные точки JSON GET |
| `index.php?api=render\|save\|upload` | конечные точки JSON POST (CSRF) |
| `admin.php?page=…` / `admin.php?action=…` | интерфейс администратора / JSON API администратора |
| `md.php?file=…` | **совместимый с v2** шим просмотрщика |
| `updater.php?update=true` · `?restore=latest` · `?api_key=…&action=…&format=json` | **совместимый с v2** шим автообновления и машинного API |

Кеширование HTTP: документы и медиафайлы отвечают заголовками `ETag`/`Last-Modified` и
поддерживают `304 Not Modified`; отрисованный HTML дополнительно кешируется на сервере с ключом
по хешу исходника × отпечатку возможностей.

---

## Встраивание и рендеринг из CLI

Конвейер Markdown можно использовать без просмотрщика: отрисовать README, примечание к релизу
или блок CMS из любого PHP-кода или shell-скрипта — с той же поддержкой CommonMark/GFM, теми же
расширениями, подсветкой и очисткой.

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

Режим встраивания выдаёт `<pre><code class="hljs language-…">` и простые заголовки, чтобы
страницу можно было оформить таблицей стилей для README на GitHub (`github-markdown-css` и тема
highlight.js). Полный справочник параметров и рецепты интеграции:
**[docs/standalone-renderer.md](docs/standalone-renderer.md)**.

Начиная с 3.2.2 тот же конвейер доступен как HTTP-точка без состояния для других приложений: на
входе Markdown, на выходе фрагмент HTML, аутентификация по ключу, без сессии:

```bash
curl -H "X-Api-Key: $API_KEY" -H 'Content-Type: application/json' \
  -d '{"content": "# Title\n\n- one\n- two", "format": "auto"}' \
  https://docs.example.com/index.php?api=render
```

`format` принимает `auto` (по умолчанию), `markdown`, `html` (с очисткой) и `text` (текст
дословно в `<div class="md-text">`). Контракт, ограничения и коды состояния:
**[docs/http-api.md](docs/http-api.md)**.

---

## Безопасность

Эшелонированная защита — подробности и контрольный список укрепления:
**[docs/security.md](docs/security.md)**.

- **Проверка путей**: 14 уровней (длина, NUL, управляющие символы, повторная проверка после
  URL-декодирования, абсолютные пути, обход каталогов, строгий белый список Unicode
  `\p{L}\p{M}\p{Pd}`, глубина, расширение, вхождение realpath, безопасная для симлинков повторная
  проверка) — один валидатор для просмотрщика, скачивания файлов, медиафайлов, загрузок, ini в
  каталогах и административной части.
- **Очистка вывода**: каждый отрисованный документ проходит очиститель на основе белого списка
  DOM (обработчики событий, опасные схемы, формы, foreignObject/`<use>` отбрасываются); авторский
  raw HTML экранируется, если только не включён `ALLOW_RAW_HTML`, — и даже тогда очищается.
- **Транспорт**: строгая CSP с одноразовыми значениями (nonce) на каждый запрос, nosniff,
  политики frame/referrer/permissions, COOP; защищённые cookie сессии.
- **Администрирование**: вход по password_hash, ограничение частоты по IP, CSRF на каждое
  изменение, перегенерация сессии, журнал аудита, мягкие удаления, первичная настройка
  допускается только с localhost или по одноразовому коду.
- **Секреты**: `.md.ini` никогда не отдаётся (`.htaccess` и схемы с docroot вне проекта);
  машинный API отвечает только JSON.

Сообщение об уязвимости: **[SECURITY.md](SECURITY.md)**.

---

## В цифрах

Измерено 2026-11-14 на дереве `main` версии 3.6.3:

| Показатель | Значение | Источник измерения |
| --- | ---: | --- |
| Исходные файлы PHP | 116 | `Get-ChildItem src,public,templates -Recurse -Filter *.php` |
| Тестовые файлы | 22 | `Get-ChildItem tests -Recurse -Filter *.php` |
| Тесты PHPUnit | 218 тестовов, 678 утверждений, 1 пропущен | `composer test` |
| Страницы документации | 12 | `Get-ChildItem docs -Filter *.md` |
| Пакеты Composer во время выполнения | 2 (`league/commonmark`, `scrivo/highlight.php`) | `composer.json` → `require` |
| Отобранные темы | 12 | `config/themes.php` |
| Запросов к CDN во время выполнения | 0 | `default-src 'self'` в действующей CSP |

---

## Разработка

```bash
composer install                 # dev deps: phpunit, phpstan
cd tools/frontend && npm install && node build.mjs && cd ../..

composer test                    # 218 PHPUnit tests, 689 assertions
composer stan                    # PHPStan level 6
php -S localhost:8080 -t public public/router.php
```

Конвейер фронтенда (Tailwind v4 → `app.css`, esbuild → `app.js`/`admin.js`, компилятор тем →
`themes.css`, генераторы иконок и эмодзи) работает только на этапе **разработки** — собранные
ресурсы коммитятся, поэтому на серверах промышленной эксплуатации Node.js не нужен. Архитектура,
карта модулей, контрольный список релиза: **[docs/development.md](docs/development.md)**.

### Структура проекта

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

## Документация

Два уровня: **[вики](https://github.com/paulmann/MD.Scribe/wiki)** — руководство по задачам
(запустить, настроить, эксплуатировать), а файлы в `docs/` — справочник, который сверяют с тегом
запущенной версии.

| Документ | Содержимое |
| --- | --- |
| **[Вики](https://github.com/paulmann/MD.Scribe/wiki)** | Начало работы, установка, настройка, возможности Markdown, поиск, руководство администратора, HTTP API, обновления, устранение неполадок, разработка, история изменений — начните с [Главной](https://github.com/paulmann/MD.Scribe/wiki/Home) |
| [docs/installation.md](docs/installation.md) | Все схемы развёртывания, Apache/nginx/Caddy/Docker, виртуальный хостинг, права доступа |
| [docs/configuration.md](docs/configuration.md) | Полный справочник `.md.ini`, файлы в каталогах, переключатели возможностей |
| [docs/markdown-features.md](docs/markdown-features.md) | Полная матрица синтаксиса с примерами |
| [docs/admin-guide.md](docs/admin-guide.md) | Каждая страница администрирования, рабочие процессы, машинный API |
| [docs/theming.md](docs/theming.md) | Система тем, токены, пользовательские темы, рецепты |
| [docs/security.md](docs/security.md) | Модель угроз, контрольный список укрепления, замечания о CSP |
| [docs/http-api.md](docs/http-api.md) | Конечные точки JSON (публичные, административные и машинный API) |
| [docs/standalone-renderer.md](docs/standalone-renderer.md) | Встраивание конвейера Markdown, CLI и HTTP API отрисовки |
| [docs/development.md](docs/development.md) | Архитектура, конвейер сборки, тестирование, релизы |
| [docs/troubleshooting.md](docs/troubleshooting.md) | Типичные проблемы и их решения |
| [docs/upgrade-from-v2.md](docs/upgrade-from-v2.md) | Сопоставление при переходе и шаги |
| [docs/AGENTS-context.md](docs/AGENTS-context.md) | Подробный контекст проекта для агентов-программистов |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Как предложить изменение и что должно пройти сначала |
| [SECURITY.md](SECURITY.md) | Поддерживаемые версии и приватное сообщение об уязвимостях |
| [CHANGELOG.md](CHANGELOG.md) | Каждый релиз, формат Keep a Changelog |

---

## Языки документации

Каноническим считается README на английском. Переведённый README повторяет этот файл раздел за
разделом и обновляется в том же коммите, когда меняется заголовок, команда или число. Вики и
`docs/` остаются на английском.

| Язык | Файл | Примечания |
| --- | --- | --- |
| Английский | [README.md](README.md) | Канонический |
| Русский | [README.ru.md](README.ru.md) | Перевод этого файла |
| Немецкий | [README.de.md](README.de.md) | Перевод этого файла |
| Китайский (упрощённый) | [README.zh.md](README.zh.md) | Перевод этого файла |
| Хинди | [README.hi.md](README.hi.md) | Перевод этого файла |
| Испанский | [README.es.md](README.es.md) | Перевод этого файла |
| Французский | [README.fr.md](README.fr.md) | Перевод этого файла |

Отставший перевод — это дефект: сообщите о нём, указав язык и раздел, или откройте pull request с
исправлением.

---

## Обновление с версии 2

Замена без переделки: разверните v3 рядом с файлами v2, перенесите документы (или укажите на них
`BROWSE_DIR`), оставьте корневой шим `md.php` — все старые закладки и ссылки `?file=` работают.
Пошагово: **[docs/upgrade-from-v2.md](docs/upgrade-from-v2.md)**.

---

## Участие в проекте

Одно логическое изменение на pull request, тест на новое поведение и никаких новых зависимостей
времени выполнения без предварительного обсуждения. Обязательные проверки — `composer test`
(218 тестовов) и `composer stan` (PHPStan уровня 6); обе должны завершиться с кодом `0`.
Соглашения, стиль коммитов, языковая политика документации и контрольный список для отчёта об
ошибке: **[CONTRIBUTING.md](CONTRIBUTING.md)**. Проект следует
[Contributor Covenant 2.1](CODE_OF_CONDUCT.md); помощь с вопросом или разбором описана в
[SUPPORT.md](SUPPORT.md).

## Лицензия

MIT — см. [LICENSE](LICENSE). Свободно для коммерческого и частного использования, изменения и
распространения при сохранении уведомления об авторских правах. Более ранние версии (вплоть до
3.3.0) поставлялись под PolyForm Noncommercial 1.0.0.
Движок Markdown: [league/commonmark](https://commonmark.thephpleague.com/) (BSD-3).
Подсветка: [scrivo/highlight.php](https://github.com/scrivo/highlight.php) (BSD-3, порт highlight.js).
Диаграммы: [Mermaid](https://mermaid.js.org/) (MIT). Формулы: [KaTeX](https://katex.org/) (MIT).
Иконки: [Lucide](https://lucide.dev/) (ISC). Эмодзи: [gemoji](https://github.com/github/gemoji) (MIT).
Шрифты: Inter и JetBrains Mono (SIL OFL).

## Помощь проекту: факты, а не активность

Что действительно помогает, в порядке ценности:

- **отчёт о совместимости**: версия PHP (`php -v`), веб-сервер и его версия, схема развёртывания
  (docroot `public/`, docroot в корне, виртуальный хостинг) и точная команда с её выводом — по
  одному отчёту на уникальное сочетание, после поиска по существующим issue;
- **исправление документации** в виде pull request с указанием файла и строки;
- **обезличенный разбор случая**, опубликованный с разрешения владельца данных;
- **замечание о зависимости или безопасности** для форка или обёртки, раскрытое там же.

Никогда не автоматизируйте звёзды, форки, подписки, комментарии, issue, pull request или массовые
сообщения сопровождающему или третьим лицам. Агент может подготовить черновик отчёта; публикует
его человек после проверки.
