<?php

/**
 * MD.Viewer 3.0 — configuration schema.
 *
 * Single source of truth for every `.md.ini` key: default value, type, section,
 * human-readable label and description. Used by:
 *  - MdViewer\Core\Config        (loading, validation, auto-append)
 *  - the admin Settings editor   (grouped form rendering + tooltips)
 *  - docs generation             (tools/build-manifest.php reads it too)
 *
 * Types: 'bool' | 'int' | 'string'. A key of type bool/int/string accepts the
 * same strict parsing rules as MD.Viewer v2 (trim, optional paired quotes,
 * reject NUL/newline/>255 bytes).
 */

declare(strict_types=1);

return [
    // ── General ────────────────────────────────────────────────────────────
    'SITE_NAME' => [
        'type' => 'string', 'default' => 'MD.Viewer', 'section' => 'general',
        'label' => 'Site name',
        'description' => 'Shown in the header, <title> and feeds.',
    ],
    'HOME_DOC' => [
        'type' => 'string', 'default' => 'index.md', 'section' => 'general',
        'label' => 'Home document',
        'description' => 'Markdown file (relative to BROWSE_DIR) rendered at the site root. Falls back to the file browser when missing.',
    ],
    'BROWSE_DIR' => [
        'type' => 'string', 'default' => '', 'section' => 'general',
        'label' => 'Browse directory',
        'description' => 'Where documents live. Empty = the bundled content/ directory. Accepts an absolute path, a DOCUMENT_ROOT-relative path, or a path relative to the project root.',
    ],

    // ── Rendering features ─────────────────────────────────────────────────
    'AUTO_TOC' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Table of contents',
        'description' => 'Auto-generate a linked table of contents from document headings.',
    ],
    'AUTO_NUMBERING' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Heading numbering',
        'description' => 'Number headings automatically (1, 1.1, 1.2 …) unless the document numbers them manually.',
    ],
    'AUTO_FOOTNOTES_LINKS' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Footnote backlinks',
        'description' => 'Add return links from footnote definitions back to their references.',
    ],
    'SPLIT_TITLE_BY_COLON' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Split hero title by colon',
        'description' => 'Render "Topic: Subtitle" as a title plus an eyebrow line.',
    ],
    'PARAGRAPH_BREAK_STYLE' => [
        'type' => 'string', 'default' => 'double-br', 'section' => 'features', 'feature' => true,
        'label' => 'Paragraph break style',
        'description' => 'double-br = single newlines become <br>; paragraph = classic Markdown spacing.',
    ],
    'DOUBLE_LINE_BREAKS' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Single newlines as line breaks',
        'description' => 'Treat a single newline inside a paragraph as a <br> (legacy MD.Viewer behaviour).',
    ],
    'FEATURE_IMAGES' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Images',
        'description' => 'Render images with lazy loading, captions and lightbox zoom.',
    ],
    'FEATURE_REF_LINKS' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Reference-style links',
        'description' => 'Support [text][ref] links with [ref]: url definitions.',
    ],
    'FEATURE_TASK_LISTS' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Task lists',
        'description' => 'Render - [ ] / - [x] items as checkboxes (GitHub style).',
    ],
    'FEATURE_FOOTNOTES' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Footnotes',
        'description' => 'Support [^1] references and [^1]: definitions.',
    ],
    'FEATURE_SUBSUP' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Superscript / subscript',
        'description' => 'Render ^superscript^ and ~subscript~ inline patterns.',
    ],
    'FEATURE_EMOJI' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Emoji shortcodes',
        'description' => 'Convert :shortcode: names (1900+ GitHub gemoji aliases) to unicode emoji.',
    ],
    'FEATURE_HIGHLIGHT_MARK' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Highlight mark',
        'description' => 'Render ==highlighted text== as <mark>.',
    ],
    'FEATURE_MATH' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Math (KaTeX)',
        'description' => 'Render $inline$ and $$block$$ LaTeX math with locally-served KaTeX.',
    ],
    'FEATURE_MERMAID' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Mermaid diagrams',
        'description' => 'Render ```mermaid fenced blocks as diagrams (locally-served Mermaid, with auto-repair).',
    ],
    'FEATURE_ALERTS' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'GitHub-style alerts',
        'description' => 'Render > [!NOTE] / [!TIP] / [!IMPORTANT] / [!WARNING] / [!CAUTION] blockquotes as callouts.',
    ],
    'FEATURE_DEFINITION_LISTS' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Definition lists',
        'description' => 'Support Term / : definition blocks (PHP-Markdown style).',
    ],
    'FEATURE_ATTRIBUTES' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Inline attributes',
        'description' => 'Support {#id .class key=value} attribute syntax on blocks and images.',
    ],
    'FEATURE_SMART_PUNCT' => [
        'type' => 'bool', 'default' => false, 'section' => 'features', 'feature' => true,
        'label' => 'Smart punctuation',
        'description' => 'Convert quotes to curly quotes, -- to en dash, ... to ellipsis.',
    ],
    'GLOSSARY_TOOLTIPS' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Glossary tooltips',
        'description' => 'Turn a "| Term | Definition |" glossary table into hover tooltips across the document.',
    ],
    'UNIVERSAL_PATTERNS' => [
        'type' => 'bool', 'default' => true, 'section' => 'features', 'feature' => true,
        'label' => 'Universal patterns',
        'description' => 'Legacy MD.Viewer inline formatting extras (kept for compatibility).',
    ],

    // ── Interface gates ────────────────────────────────────────────────────
    'FEATURE_DOWNLOAD_ICON' => [
        'type' => 'bool', 'default' => true, 'section' => 'interface', 'feature' => true,
        'label' => 'Download icon (browser rows)',
        'description' => 'Per-row download control in the file browser.',
    ],
    'FEATURE_DOWNLOAD_BUTTON' => [
        'type' => 'bool', 'default' => true, 'section' => 'interface', 'feature' => true,
        'label' => 'Download button (toolbar)',
        'description' => 'Download the source .md from the document toolbar.',
    ],
    'FEATURE_COPY_BUTTON' => [
        'type' => 'bool', 'default' => true, 'section' => 'interface', 'feature' => true,
        'label' => 'Copy source button',
        'description' => 'Copy the document source .md to the clipboard from the toolbar.',
    ],
    'FEATURE_SEARCH' => [
        'type' => 'bool', 'default' => true, 'section' => 'interface', 'feature' => true,
        'label' => 'Search',
        'description' => 'Full-text search over all documents (server-side index, no database).',
    ],
    'FEATURE_COMMAND_PALETTE' => [
        'type' => 'bool', 'default' => true, 'section' => 'interface', 'feature' => true,
        'label' => 'Command palette (Ctrl+K)',
        'description' => 'Quick-jump palette: files, headings, actions, themes.',
    ],
    'FEATURE_LIGHTBOX' => [
        'type' => 'bool', 'default' => true, 'section' => 'interface', 'feature' => true,
        'label' => 'Image lightbox',
        'description' => 'Click-to-zoom overlay for document images.',
    ],
    'FEATURE_READING_PROGRESS' => [
        'type' => 'bool', 'default' => true, 'section' => 'interface', 'feature' => true,
        'label' => 'Reading progress bar',
        'description' => 'Thin progress indicator under the header while scrolling.',
    ],

    // ── Uploads & clipboard ────────────────────────────────────────────────
    'DISABLE_UPLOAD' => [
        'type' => 'bool', 'default' => true, 'section' => 'content',
        'label' => 'Disable uploads (reader UI)',
        'description' => 'Hides and server-blocks the "Upload .md" control in the file browser. Per-directory .md.ini may override.',
    ],
    'DISABLE_CLIPBOARD' => [
        'type' => 'bool', 'default' => false, 'section' => 'content',
        'label' => 'Disable clipboard preview',
        'description' => 'Hides and server-blocks the clipboard preview in the file browser.',
    ],
    'DISABLE_SAVE_CLIPBOARD_TO_FILE' => [
        'type' => 'bool', 'default' => true, 'section' => 'content',
        'label' => 'Disable "save clipboard to file"',
        'description' => 'Hides and server-blocks saving pasted clipboard content as a new .md file.',
    ],
    'MAX_UPLOAD_MB' => [
        'type' => 'int', 'default' => 8, 'section' => 'content',
        'label' => 'Max upload size (MB)',
        'description' => 'Hard cap for reader uploads and admin uploads (also capped by PHP upload_max_filesize).',
    ],

    // ── Security & admin ───────────────────────────────────────────────────
    'ADMIN_ENABLED' => [
        'type' => 'bool', 'default' => true, 'section' => 'security',
        'label' => 'Admin panel enabled',
        'description' => 'Master switch for /admin (dashboard, file manager, backups, updater…). Set false to serve docs only.',
    ],
    'ADMIN_USERNAME' => [
        'type' => 'string', 'default' => 'admin', 'section' => 'security',
        'label' => 'Admin username',
        'description' => 'Login name for the admin panel.',
    ],
    'ADMIN_PASSWORD' => [
        'type' => 'string', 'default' => '', 'section' => 'security', 'secret' => true,
        'label' => 'Admin password (bootstrap)',
        'description' => 'Set a plaintext password here once; MD.Viewer hashes it into ADMIN_PASSWORD_HASH on next load and clears this key.',
    ],
    'ADMIN_PASSWORD_HASH' => [
        'type' => 'string', 'default' => '', 'section' => 'security', 'secret' => true,
        'label' => 'Admin password hash',
        'description' => 'password_hash() output. Managed by the admin panel — do not edit by hand.',
    ],
    'ADMIN_RATE_LIMIT' => [
        'type' => 'int', 'default' => 10, 'section' => 'security',
        'label' => 'Login rate limit (attempts / 15 min)',
        'description' => 'Failed admin logins allowed per IP per 15 minutes before temporary lockout.',
    ],
    'API_KEY' => [
        'type' => 'string', 'default' => '', 'section' => 'security', 'secret' => true, 'autogenerate' => 'api_key',
        'label' => 'Machine API key',
        'description' => 'Credential for the JSON machine API (status/conflicts/install). Generated on first run.',
    ],
    'ALLOW_UPDATE' => [
        'type' => 'bool', 'default' => false, 'section' => 'security',
        'label' => 'Allow self-update',
        'description' => 'Enables checking and applying updates from GitHub (admin panel + updater shim).',
    ],
    'ALLOW_RESTORE' => [
        'type' => 'bool', 'default' => false, 'section' => 'security',
        'label' => 'Allow backup restore',
        'description' => 'Enables listing and restoring backups.',
    ],
    'ALLOW_CREATE_INDEX_PHP_LINK' => [
        'type' => 'bool', 'default' => true, 'section' => 'security',
        'label' => 'Allow index.php link management',
        'description' => 'Enables creating/removing the root index.php shortcut from the admin panel.',
    ],
    'ALLOW_RAW_HTML' => [
        'type' => 'bool', 'default' => false, 'section' => 'security',
        'label' => 'Allow raw HTML in documents',
        'description' => 'Keep sanitized author HTML (allowlisted tags/attributes). When false, raw HTML is escaped/stripped. Output is always sanitized.',
    ],
    'ALLOW_IFRAMES' => [
        'type' => 'bool', 'default' => false, 'section' => 'security',
        'label' => 'Allow iframes / embeds',
        'description' => 'Permit <iframe>, <video>, <audio> and <embed> in documents (only meaningful with ALLOW_RAW_HTML).',
    ],
    'ALLOW_EXTERNAL_IMAGES' => [
        'type' => 'bool', 'default' => true, 'section' => 'security',
        'label' => 'Allow external images',
        'description' => 'When false, images pointing to other origins are replaced with a placeholder note.',
    ],
    'SECURITY_HEADERS' => [
        'type' => 'bool', 'default' => true, 'section' => 'security',
        'label' => 'Send security headers',
        'description' => 'X-Content-Type-Options, X-Frame-Options, Referrer-Policy, Permissions-Policy.',
    ],
    'SECURITY_CSP' => [
        'type' => 'bool', 'default' => true, 'section' => 'security',
        'label' => 'Send Content-Security-Policy',
        'description' => 'Strict CSP with per-request nonces for inline scripts/styles.',
    ],
    'TRUSTED_PROXIES' => [
        'type' => 'string', 'default' => '', 'section' => 'security',
        'label' => 'Trusted proxies (CSV)',
        'description' => 'Comma-separated proxy IPs allowed to set X-Forwarded-For. Empty = ignore forwarded headers.',
    ],

    // ── Performance ────────────────────────────────────────────────────────
    'CACHE_ENABLED' => [
        'type' => 'bool', 'default' => true, 'section' => 'performance',
        'label' => 'Render cache',
        'description' => 'Cache rendered documents keyed by source hash + feature set. Invalidated automatically on file change.',
    ],
    'CACHE_TTL' => [
        'type' => 'int', 'default' => 3600, 'section' => 'performance',
        'label' => 'Browser cache TTL (seconds)',
        'description' => 'Cache-Control max-age for rendered documents and static assets.',
    ],
    'MAX_SCAN_DEPTH' => [
        'type' => 'int', 'default' => 8, 'section' => 'performance',
        'label' => 'Max scan depth',
        'description' => 'Directory depth limit for the file browser and search index.',
    ],
    'MAX_FILES_SCAN' => [
        'type' => 'int', 'default' => 10000, 'section' => 'performance',
        'label' => 'Max files scanned',
        'description' => 'Safety cap for the recursive scanner.',
    ],
    'SEARCH_MAX_RESULTS' => [
        'type' => 'int', 'default' => 50, 'section' => 'performance',
        'label' => 'Max search results',
        'description' => 'Cap for the search page and palette results.',
    ],

    // ── Feeds & discovery ──────────────────────────────────────────────────
    'SITEMAP_ENABLED' => [
        'type' => 'bool', 'default' => true, 'section' => 'feeds',
        'label' => 'sitemap.xml',
        'description' => 'Serve an auto-generated sitemap of all documents.',
    ],
    'RSS_ENABLED' => [
        'type' => 'bool', 'default' => true, 'section' => 'feeds',
        'label' => 'RSS feed',
        'description' => 'Serve feed.xml with recently modified documents.',
    ],

    // ── Appearance defaults (client preferences still win) ────────────────
    'THEME_DEFAULT' => [
        'type' => 'string', 'default' => 'zinc', 'section' => 'appearance',
        'label' => 'Default theme',
        'description' => 'Base theme applied on first visit (before the visitor picks one). See the Themes catalog.',
    ],
    'MODE_DEFAULT' => [
        'type' => 'string', 'default' => 'system', 'section' => 'appearance',
        'label' => 'Default color mode',
        'description' => 'light | dark | system — used on first visit only.',
    ],

    // ── Browser table defaults ─────────────────────────────────────────────
    'SORT_DEFAULT_KEY' => [
        'type' => 'string', 'default' => 'modified', 'section' => 'appearance',
        'label' => 'Default sort column',
        'description' => 'name | size | modified — initial order of the file browser table.',
    ],
    'SORT_DEFAULT_DIR' => [
        'type' => 'string', 'default' => 'desc', 'section' => 'appearance',
        'label' => 'Default sort direction',
        'description' => 'asc | desc.',
    ],

    // ── Diagnostics ────────────────────────────────────────────────────────
    'APP_ENV' => [
        'type' => 'string', 'default' => 'production', 'section' => 'diagnostics',
        'label' => 'Environment',
        'description' => 'production | development. Development shows detailed error pages.',
    ],
    'APP_DEBUG' => [
        'type' => 'bool', 'default' => false, 'section' => 'diagnostics',
        'label' => 'Debug mode',
        'description' => 'Log debug-level messages and show stack traces on error pages. Never enable on a public server.',
    ],
    'LOG_LEVEL' => [
        'type' => 'string', 'default' => 'warning', 'section' => 'diagnostics',
        'label' => 'Log level',
        'description' => 'debug | info | warning | error — minimum level written to storage/logs.',
    ],
    'LOG_RETENTION_DAYS' => [
        'type' => 'int', 'default' => 14, 'section' => 'diagnostics',
        'label' => 'Log retention (days)',
        'description' => 'Older daily log files are deleted automatically.',
    ],
];
