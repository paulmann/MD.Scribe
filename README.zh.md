<!--
 * MD.Scribe — Documentation
 * Version: 3.6.2
-->

# MD.Scribe 3

MD.Scribe 是一个用 PHP 8.5 构建的自托管 Markdown 文档平台：一个完整支持 CommonMark/GFM 的查看器，
带全文搜索、命令面板、主题目录、Mermaid 图表与 KaTeX 数学公式，另有一个管理面板，包含内容管理器、
编辑器、备份、经校验的自更新程序和审计追踪。它不需要数据库、不需要框架，服务器上也不需要构建步骤，
所有资源都在本地随包提供，因此整个应用可以离线运行。

<!-- md-i18n:switcher -->
**其他语言：** [English](README.md) · [Deutsch](README.de.md) · [Español](README.es.md) · [Français](README.fr.md) · [हिन्दी](README.hi.md) · 中文 · [Русский](README.ru.md)

[![CI](https://github.com/paulmann/MD.Scribe/actions/workflows/ci.yml/badge.svg)](https://github.com/paulmann/MD.Scribe/actions/workflows/ci.yml)
[![发布](https://img.shields.io/github/v/release/paulmann/MD.Scribe?label=release&sort=semver)](https://github.com/paulmann/MD.Scribe/releases)
[![许可证：MIT](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE)
[![PHP 8.5](https://img.shields.io/badge/PHP-8.5-777BB4?logo=php&logoColor=white)](https://php.net)
[![测试](https://img.shields.io/badge/tests-236%20passed-brightgreen)](tests)
[![CDN 请求](https://img.shields.io/badge/CDN%20requests-0-success)](#零-cdn-策略)

<img src="https://github.com/paulmann/MD.Scribe/raw/main/assets/social-preview.png"
     alt="MD.Scribe —— 自托管的 PHP 8.5 Markdown 文档平台，含查看器与管理面板"
     width="100%">

[**快速开始**](#快速开始) · [**功能导览**](#功能导览) · [**配置**](#配置--mdini) · [**文档**](#文档) · [**Wiki**](https://github.com/paulmann/MD.Scribe/wiki) · [**AGENTS.md**](AGENTS.md) · [**更新日志**](CHANGELOG.md) · [**安全性**](SECURITY.md) · [**参与贡献**](CONTRIBUTING.md)

> **文档状态。** 本 README 中的版本号和每一个数字都于 2026-11-14 针对 3.6.0 的 `main` 树测得，
> 并在数字出现处注明所用的命令。面向任务的指南位于
> [wiki](https://github.com/paulmann/MD.Scribe/wiki/Home)；参考文档位于
> [`docs/`](docs/installation.md)。

## 目录

- [什么是 MD.Scribe？](#什么是-mdscribe)
- [快速开始](#快速开始)
- [功能导览](#功能导览)
- [配置 — `.md.ini`](#配置--mdini)
- [URL 约定](#url-约定)
- [嵌入与 CLI 渲染](#嵌入与-cli-渲染)
- [安全性](#安全性)
- [数字一览](#数字一览)
- [开发](#开发)
- [文档](#文档)
- [文档语言](#文档语言)
- [从 v2 升级](#从-v2-升级)
- [参与贡献](#参与贡献)
- [许可证](#许可证)
- [帮助项目：证据，而非互动](#帮助项目证据而非互动)

---

## 什么是 MD.Scribe？

让它指向一个装满 `.md` 文件的文件夹，你就得到了一个文档站点：

- **`public/index.php`** 渲染单个文档（`?file=path/doc.md`）；没有请求具体文件时渲染可搜索的
  **文档库**，另外还有**主题**、**设置**和**搜索**页面，以及 `sitemap.xml` 和 RSS 订阅。
- **`public/admin.php`** 是管理面板：仪表盘、文件管理器、带实时预览的分栏编辑器、上传、
  搜索索引控制、备份与回收站、自更新程序、设置编辑器、日志与审计追踪、安全与健康检查，
  以及若干工具（死链检查器、缓存控制）。
- 项目根目录的 **`md.php` / `updater.php`** 是兼容层——升级之后，每一个 v2 URL 约定
  （`?file=`、`&download=1`、`?update=true`、`?restore=latest`、机器 API）都继续可用。

**初次使用？** 从 [wiki](https://github.com/paulmann/MD.Scribe/wiki/Getting-Started)
（[首页](https://github.com/paulmann/MD.Scribe/wiki/Home)）开始——它用四条命令把你从发布归档
带到一次经过验证的安装。

所有样式都由**基于本地 Tailwind CSS v4 构建的 shadcn/ui 令牌设计系统**统一驱动，支持
浅色/深色/跟随系统三种模式、12 套精选主题，以及中性色 × 强调色的混配。Mermaid、KaTeX、
Inter、JetBrains Mono 和全部图标均已内置——应用可以完全**离线 / 隔离网络**运行。

---

## 快速开始

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

生产部署（文档根指向 `public/`、文档根指向项目根、Apache、nginx、Caddy、Docker、共享主机）：
**[docs/installation.md](docs/installation.md)**。

环境要求：**PHP ≥ 8.5**，并启用 `mbstring`、`dom`、`libxml`、`json`（自更新程序和备份归档
建议同时启用 `curl` + `phar` + `zip`）。

---

## 功能导览

### 阅读体验

| 方面 | 功能 |
| --- | --- |
| 排版 | 流式字号阶梯，字号 / 页面宽度 / 行高控件，衬线与系统字体选项，打印样式表 |
| 导航 | 自动目录并高亮当前章节，标题编号 + 悬停锚点，`[TOC]` 占位符，面包屑，上一篇/下一篇翻页，文件夹侧栏，跳过导航链接 |
| 发现 | 服务端**全文搜索**（倒排索引，支持 CJK）与**运算符**（`tag:`、`path:`、带引号的值）、**Ctrl+K 命令面板**（文档、标题、操作）、**标签索引**（`?page=tags`）、sitemap.xml、RSS 订阅 |
| 富内容 | 服务端**语法高亮**（约 190 种语言）、带自动修复与诊断的 **Mermaid** 图表、**KaTeX** 数学公式（含 mhchem）、GitHub 风格**提示块**、任务列表、脚注、定义列表、`==marks==`、^super^/~sub~ 上下标、1,900+ 表情短码、带说明文字的图片**灯箱**、阅读时长估算 |
| 知识库 | **Wiki 链接**（`[[Page]]`、`[[Page\|Alias]]`、`[[Page#Heading]]`）与缺失页面标记，**反向链接**（“Linked from”）、frontmatter **标签**及公开索引、按文档的**修订历史**（可还原）、**静态站点导出**为自包含 ZIP |
| 术语表 | 任意两列表格都会变成悬停/点按式**提示气泡**，支持别名展开（`Term (ABBR)`），内容取自实时 DOM——零重复 |
| 离线 | 可安装的 **PWA**：web manifest + service worker——资源缓存优先，文档网络优先并以最后读取的副本作为回退，JSON API 永不缓存（`PWA_ENABLED`） |
| 主题 | 12 套预设 + 中性色 × 强调色 × 圆角 × 字体混配，浅色/深色/**自动跟随系统**，按访客持久保存，由管理面板管理的**自定义主题**，绘制前引导脚本无 FOUC |

### Markdown 支持

借助 `league/commonmark` 完整支持 CommonMark **0.31**，另有 GFM（带对齐的表格、删除线、
任务列表、自动链接）、脚注（引用式**与**行内式）、定义列表、属性语法 `{#id .class}`、
智能标点（可选开启）、YAML frontmatter（`title`、`description`、`tags`、`author`、`date`、
`draft`、`toc`、`numbering`）、相对 `.md` 链接改写并标记死链、`[[wikilinks]]` 支持别名/锚点
解析，以及旧版 MD.Viewer 的扩展（通用模式链、术语表、`md.md` 命名规则）。完整对照表：
**[docs/markdown-features.md](docs/markdown-features.md)**。

### 管理面板

| 页面 | 作用 |
| --- | --- |
| **仪表盘** | 内容库统计、索引新鲜度、健康摘要、快捷操作、最近文档、活动流 |
| **文件** | 全部内容的树形视图、过滤、创建/重命名/移动/删除（软删除进**回收站**）、文件夹、多文件上传（.md + 带内容嗅探的图片）、**从模板新建** |
| **编辑器** | 分栏实时预览（使用*真实*渲染引擎）、**Markdown 工具栏**（粗体、斜体、代码、链接、wiki 链接、列表、提示块、表格）、⌘S 保存并做 **sha256 冲突检测**、字数/阅读时长计数、在光标处上传图片、深链接 `?page=editor&file=…` |
| **搜索索引** | 统计、新鲜度、按需重建 |
| **备份与回收站** | 一键内容快照（含 `.md.ini`）、还原时自动生成还原前安全副本、ZIP 下载、可清理的列表、已删除文件还原 |
| **修订** | 按文档的保存历史（保留最新 `REVISIONS_MAX` 条，默认 20）、只读预览、还原时把当前内容保留为 `(pre-restore)` 修订 |
| **更新** | GitHub 发布检查（缓存 15 分钟）、发布说明、**经验证的应用**（tarball + 逐文件 sha256 清单，先备份）、机器 API 文档 |
| **设置** | 每个 `.md.ini` 键都对应一个带类型的表单字段（模式驱动）、API 密钥重新生成、管理员密码修改 |
| **自定义主题** | 保存品牌强调色（浅色 + 深色主色）——在公开主题目录中立刻可选 |
| **日志与审计** | 每日应用日志 + JSON 审计追踪（谁在何时、从哪里做了什么） |
| **安全与健康** | 20+ 项检查：PHP 版本/扩展、可写性、权限、密钥状态、OPcache、CSP；加固清单 |
| **工具** | **死链 / 失效锚点 / 缺失媒体 / 孤立文档检查器**、反向链接与标签索引重建、**静态站点导出**（自包含 HTML 的 ZIP）、清理缓存、订阅与 sitemap 快捷入口 |

### 零 CDN 策略

所有资源都在本地随包提供：Tailwind 产物（开发时构建并提交）、内置的 `mermaid.min.js`、
KaTeX（CSS + 字体 + mhchem）、Inter 与 JetBrains Mono 的 woff2 子集
（latin/latin-ext/cyrillic）、编译进 PHP sprite 映射表的 lucide 图标，以及完整的 gemoji
数据库。内容安全策略强制保证这一点：`default-src 'self'`。

---

## 配置 — `.md.ini`

首次加载时会在项目根目录旁自动创建。安装级键在每次加载时都会以安全默认值追加——升级从不需要
手工编辑。渲染功能相关的键刻意**不**写入：它们的默认值放在 `config/defaults.php`，访客可以
按浏览器切换（第一方 cookie）。把某个功能键写进 `.md.ini` 会将其**硬锁定**。

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

完整键参考（每个键、类型、默认值、作用）：**[docs/configuration.md](docs/configuration.md)**。
按目录分层的 `.md.ini`（v2 的 `DISABLE_*` 机制）仍然保留：
**[docs/configuration.md#per-directory](docs/configuration.md#per-directory-mdini)**。

---

## URL 约定

| URL | 用途 |
| --- | --- |
| `index.php?file=docs/api.md` | 渲染一个文档 |
| `index.php?file=docs/api.md&download=1` | 源文件下载（octet-stream + nosniff） |
| `index.php?media=docs/img/pic.png` | 内容资源（图片内联；SVG/ZIP 作为附件） |
| `index.php?page=browser[&dir=docs]` | 文档库 / 文件浏览器 |
| `index.php?page=search&q=…` | 全文搜索 |
| `index.php?page=themes` / `page=settings` | 主题目录 / 设置 |
| `index.php?page=sitemap` / `page=feed` | sitemap.xml / RSS |
| `index.php?api=search\|suggest\|toc\|config` | JSON GET 端点 |
| `index.php?api=render\|save\|upload` | JSON POST 端点（CSRF） |
| `admin.php?page=…` / `admin.php?action=…` | 管理界面 / 管理 JSON API |
| `md.php?file=…` | **v2 兼容**的查看器兼容层 |
| `updater.php?update=true` · `?restore=latest` · `?api_key=…&action=…&format=json` | **v2 兼容**的更新程序 + 机器 API |

HTTP 缓存：文档与媒体响应带有 `ETag`/`Last-Modified`，并支持 `304 Not Modified`；渲染后的
HTML 还会在服务端缓存，键由源文件哈希 × 功能指纹组成。

---

## 嵌入与 CLI 渲染

Markdown 处理管线可以脱离查看器单独使用：从任意 PHP 调用方或 shell 脚本渲染 README、
发布说明或 CMS 区块，共用同一套 CommonMark/GFM 支持、扩展、语法高亮与净化器。

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

嵌入模式输出 `<pre><code class="hljs language-…">` 和朴素的标题，以便 GitHub-README 样式表
（`github-markdown-css` + 一个 highlight.js 主题）为页面设置样式。完整选项参考与集成示例：
**[docs/standalone-renderer.md](docs/standalone-renderer.md)**。

从 3.2.2 起，同一管线还是一个供其他应用使用的无状态 HTTP 端点——输入 Markdown，输出 HTML
片段，密钥认证，无会话：

```bash
curl -H "X-Api-Key: $API_KEY" -H 'Content-Type: application/json' \
  -d '{"content": "# Title\n\n- one\n- two", "format": "auto"}' \
  https://docs.example.com/index.php?api=render
```

`format` 接受 `auto`（默认）、`markdown`、`html`（已净化）和 `text`（把纯文本原样包进
`<div class="md-text">`）。约定、限制与状态码：**[docs/http-api.md](docs/http-api.md)**。

---

## 安全性

纵深防御——细节与加固清单见 **[docs/security.md](docs/security.md)**。

- **路径校验**：14 层（长度、NUL、控制字符、URL 解码后复查、绝对路径、目录穿越、严格的
  Unicode 白名单 `\p{L}\p{M}\p{Pd}`、深度、扩展名、realpath 包含关系、符号链接安全复查）——
  查看器、下载、媒体、上传、按目录 ini 和管理面板共用同一个校验器。
- **输出净化**：每个渲染出的文档都要经过基于 DOM 允许列表的净化器（事件处理器、危险协议、
  表单、foreignObject/`<use>` 一律清除）；作者的原始 HTML 默认转义，除非开启
  `ALLOW_RAW_HTML`——即便如此仍会被净化。
- **传输**：严格 CSP 并按请求生成 nonce、nosniff、frame/referrer/permissions 策略、COOP；
  加固的会话 cookie。
- **管理**：password_hash 登录、按 IP 限流、每次变更都校验 CSRF、会话重新生成、审计追踪、
  软删除、首次运行安装仅限本机或一次性验证码。
- **密钥**：`.md.ini` 永不对外提供（`.htaccess` + 文档根之外的部署布局）；机器 API 只返回
  JSON。

报告漏洞：**[SECURITY.md](SECURITY.md)**。

---

## 数字一览

于 2026-11-14 针对 3.6.0 的 `main` 树测得：

| 方面 | 数值 | 测量来源 |
| --- | ---: | --- |
| PHP 源文件 | 116 | `Get-ChildItem src,public,templates -Recurse -Filter *.php` |
| 测试文件 | 22 | `Get-ChildItem tests -Recurse -Filter *.php` |
| PHPUnit 测试 | 214 个测试，678 条断言，1 个跳过 | `composer test` |
| 文档页面 | 12 | `Get-ChildItem docs -Filter *.md` |
| 运行时 Composer 包 | 2 个（`league/commonmark`、`scrivo/highlight.php`） | `composer.json` → `require` |
| 精选主题 | 12 | `config/themes.php` |
| 运行时 CDN 请求 | 0 | 已生效的 CSP 中的 `default-src 'self'` |

---

## 开发

```bash
composer install                 # dev deps: phpunit, phpstan
cd tools/frontend && npm install && node build.mjs && cd ../..

composer test                    # 214 PHPUnit tests, 678 assertions
composer stan                    # PHPStan level 6
php -S localhost:8080 -t public public/router.php
```

前端管线（Tailwind v4 → `app.css`，esbuild → `app.js`/`admin.js`，主题编译器 →
`themes.css`，图标/表情生成器）只在**开发**时运行——构建产物已提交，所以生产服务器不需要
Node.js。架构、模块地图、发布清单：**[docs/development.md](docs/development.md)**。

### 项目结构

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

## 文档

分两层：**[wiki](https://github.com/paulmann/MD.Scribe/wiki)** 是面向任务的指南（跑起来、
配置、运维），而 `docs/` 里的文件是你对照所运行标签查阅的参考文档。

| 文档 | 内容 |
| --- | --- |
| **[Wiki](https://github.com/paulmann/MD.Scribe/wiki)** | 入门、安装、配置、Markdown 特性、搜索、管理指南、HTTP API、更新、故障排查、开发、更新日志——从[首页](https://github.com/paulmann/MD.Scribe/wiki/Home)开始 |
| [docs/installation.md](docs/installation.md) | 所有部署布局、Apache/nginx/Caddy/Docker、共享主机、权限 |
| [docs/configuration.md](docs/configuration.md) | 完整的 `.md.ini` 参考、按目录文件、功能开关 |
| [docs/markdown-features.md](docs/markdown-features.md) | 带示例的完整语法对照表 |
| [docs/admin-guide.md](docs/admin-guide.md) | 每个管理页面、工作流程、机器 API |
| [docs/theming.md](docs/theming.md) | 主题系统、令牌、自定义主题、示例 |
| [docs/security.md](docs/security.md) | 威胁模型、加固清单、CSP 说明 |
| [docs/http-api.md](docs/http-api.md) | JSON 端点（公开 + 管理 + 机器 API） |
| [docs/standalone-renderer.md](docs/standalone-renderer.md) | 嵌入 Markdown 管线、CLI 与 HTTP 渲染 API |
| [docs/development.md](docs/development.md) | 架构、构建管线、测试、发布 |
| [docs/troubleshooting.md](docs/troubleshooting.md) | 常见问题与修复 |
| [docs/upgrade-from-v2.md](docs/upgrade-from-v2.md) | 迁移映射与步骤 |
| [docs/AGENTS-context.md](docs/AGENTS-context.md) | 面向编码智能体的深度项目上下文 |
| [CONTRIBUTING.md](CONTRIBUTING.md) | 如何提出改动，以及必须先通过哪些检查 |
| [SECURITY.md](SECURITY.md) | 受支持的版本与私密漏洞报告 |
| [CHANGELOG.md](CHANGELOG.md) | 每一次发布，Keep a Changelog 格式 |

---

## 文档语言

英文 README 是权威版本。译文 README 逐节对应本文件，并在标题、命令或数字发生变化时于同一次
提交中更新。wiki 与 `docs/` 保持英文。

| 语言 | 文件 | 备注 |
| --- | --- | --- |
| 英语 | [README.md](README.md) | 权威版本 |
| 俄语 | [README.ru.md](README.ru.md) | 本文件的译文 |
| 德语 | [README.de.md](README.de.md) | 本文件的译文 |
| 简体中文 | [README.zh.md](README.zh.md) | 本文件的译文 |
| 印地语 | [README.hi.md](README.hi.md) | 本文件的译文 |
| 西班牙语 | [README.es.md](README.es.md) | 本文件的译文 |
| 法语 | [README.fr.md](README.fr.md) | 本文件的译文 |

译文落后于原文就是缺陷：请带着语言和章节名称报告它，或者提交一个修复它的拉取请求。

---

## 从 v2 升级

可直接替换：把 v3 部署到 v2 文件旁边，迁移（或让 `BROWSE_DIR` 指向）你的文档，保留根目录的
`md.php` 兼容层——每个旧书签和 `?file=` 链接都能用。分步说明：
**[docs/upgrade-from-v2.md](docs/upgrade-from-v2.md)**。

---

## 参与贡献

每个拉取请求只做一处逻辑改动，新行为要配测试，引入新的运行时依赖前必须先讨论。门禁是
`composer test`（214 个测试）和 `composer stan`（PHPStan level 6），两者都必须以 `0` 退出。
约定、提交风格、文档语言政策和缺陷报告清单：**[CONTRIBUTING.md](CONTRIBUTING.md)**。本项目
遵循 [Contributor Covenant 2.1](CODE_OF_CONDUCT.md)；如何提供问题解答或参与初筛见
[SUPPORT.md](SUPPORT.md)。

## 许可证

MIT——见 [LICENSE](LICENSE)。可自由用于商业与私人用途、修改和再分发，需保留版权声明。更早的
版本（直到 3.3.0）以 PolyForm Noncommercial 1.0.0 发布。
Markdown 引擎：[league/commonmark](https://commonmark.thephpleague.com/)（BSD-3）。
语法高亮：[scrivo/highlight.php](https://github.com/scrivo/highlight.php)（BSD-3，highlight.js 移植）。
图表：[Mermaid](https://mermaid.js.org/)（MIT）。数学公式：[KaTeX](https://katex.org/)（MIT）。
图标：[Lucide](https://lucide.dev/)（ISC）。表情数据：[gemoji](https://github.com/github/gemoji)（MIT）。
字体：Inter 与 JetBrains Mono（SIL OFL）。

## 帮助项目：证据，而非互动

真正有帮助的事情，按价值排序：

- 一份**兼容性报告**：PHP 版本（`php -v`）、Web 服务器及其版本、部署布局（文档根为
  `public/`、文档根在项目根、共享主机），以及确切命令和它的输出——先搜索已有 issue，
  每种唯一组合各报一份；
- 一处**文档修正**，以拉取请求形式提交，并指明文件与行号；
- 一份经脱敏的**案例研究**，在数据所有者许可后发布；
- 一条针对分支或封装层的**依赖或安全提示**，在相同位置披露。

绝不要自动化 star、fork、watch、评论、issue、拉取请求，或向维护者和第三方批量发送消息。
智能体可以起草报告，由人在审阅后发布。
