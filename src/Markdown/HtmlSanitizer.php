<?php

/**
 * MD.Viewer 3.0 — HTML sanitizer.
 *
 * Defense in depth: even though league/commonmark escapes raw HTML by default
 * and blocks unsafe links (allow_unsafe_links=false), EVERY rendered document
 * passes through this DOM allowlist sanitizer before it reaches the browser.
 * It also cleans up author HTML when ALLOW_RAW_HTML is on.
 *
 * Rules:
 *  - tags not on the allowlist are unwrapped (children kept) or removed with
 *    children when dangerous (script, style, form, object, …);
 *  - attributes not on the allowlist are dropped — every on* handler dies here;
 *  - href/src accept fragments, relative URLs, http(s), mailto, tel; data:
 *    only for raster images; anything else (javascript:, vbscript:) is dropped;
 *  - <iframe>/<video>/<audio>/<embed> require ALLOW_IFRAMES;
 *  - <svg> requires ALLOW_RAW_HTML and carries its own child allowlist;
 *  - style attributes are dropped unless ALLOW_RAW_HTML, and even then are
 *    scrubbed of expression()/behavior/url(javascript:) payloads.
 */

declare(strict_types=1);

namespace MdViewer\Markdown;

final class HtmlSanitizer
{
    /** Tags removed together with their children. */
    private const array DROP_WITH_CONTENT = [
        'script', 'style', 'noscript', 'template', 'object', 'form',
        'select', 'option', 'optgroup', 'textarea', 'meta',
        'link', 'base', 'head', 'title', 'applet', 'param', 'map', 'area',
        'canvas', 'dialog', 'slot', 'portal', 'frame', 'frameset', 'noembed',
    ];

    /** Tags kept only when ALLOW_IFRAMES. */
    private const array EMBED_TAGS = ['iframe', 'video', 'audio', 'embed'];

    /** The plain-tag allowlist (children kept, tag attributes filtered). */
    private const array ALLOWED_TAGS = [
        'a', 'abbr', 'address', 'article', 'aside', 'b', 'bdi', 'bdo',
        'blockquote', 'br', 'button', 'caption', 'cite', 'code', 'col',
        'colgroup', 'data', 'dd', 'del', 'details', 'dfn', 'div', 'dl', 'dt',
        'em', 'figcaption', 'figure', 'footer', 'h1', 'h2', 'h3', 'h4', 'h5',
        'h6', 'header', 'hgroup', 'hr', 'i', 'img', 'input', 'ins', 'kbd',
        'li', 'main', 'mark', 'nav', 'ol', 'p', 'picture', 'pre', 'q', 'rp',
        'rt', 'ruby', 's', 'samp', 'section', 'small', 'source', 'span',
        'strong', 'sub', 'summary', 'sup', 'table', 'tbody', 'td', 'tfoot',
        'th', 'thead', 'time', 'tr', 'u', 'ul', 'var', 'wbr',
    ];

    /** SVG subtree allowlist — inert graphics only: no foreignObject, no
     *  <use>, no <image>, no scripts, no event handlers (attributes filtered).
     *  Author SVG only reaches here with ALLOW_RAW_HTML (otherwise raw HTML is
     *  escaped upstream); the app's own icons pass by design. */
    private const array SVG_TAGS = [
        'svg', 'g', 'path', 'circle', 'ellipse', 'rect', 'line', 'polyline',
        'polygon', 'text', 'tspan', 'defs', 'lineargradient', 'radialgradient',
        'stop', 'marker', 'desc', 'title', 'clippath', 'mask', 'pattern',
        'textpath', 'symbol',
    ];

    private const array GLOBAL_ATTRS = ['class', 'title', 'lang', 'dir', 'role'];

    private const array TAG_ATTRS = [
        'a' => ['href', 'rel', 'target', 'name', 'download'],
        'img' => ['src', 'alt', 'width', 'height', 'loading', 'decoding', 'srcset', 'sizes'],
        'source' => ['src', 'srcset', 'type', 'media', 'sizes'],
        'input' => ['type', 'checked', 'disabled'],
        'td' => ['colspan', 'rowspan', 'align', 'data-gc'],
        'th' => ['colspan', 'rowspan', 'align', 'scope'],
        'col' => ['span', 'width'],
        'ol' => ['start', 'type', 'reversed'],
        'ul' => ['type'],
        'li' => ['value'],
        'time' => ['datetime'],
        'del' => ['datetime', 'cite'],
        'ins' => ['datetime', 'cite'],
        'details' => ['open'],
        'blockquote' => ['cite'],
        'q' => ['cite'],
        'data' => ['value'],
        'video' => ['src', 'controls', 'width', 'height', 'poster', 'preload', 'loop', 'muted', 'playsinline'],
        'audio' => ['src', 'controls', 'preload', 'loop', 'muted'],
        'iframe' => ['src', 'width', 'height', 'allowfullscreen', 'loading', 'title'],
        'embed' => ['src', 'width', 'height', 'type'],
        'pre' => ['data-lang'],
        'code' => ['data-lang'],
        'button' => ['type', 'aria-label', 'data-copy-type', 'data-action'],
    ];

    /** id is allowed on these tags (anchors, footnotes, glossary cells). */
    private const array ID_TAGS = [
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'a', 'p', 'li', 'ol', 'ul', 'div',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'sup', 'sub',
        'section', 'details', 'summary', 'figure', 'figcaption', 'span', 'dl',
        'dt', 'dd', 'blockquote', 'pre', 'code', 'img', 'del', 'ins', 'em', 'strong',
    ];

    /** data-* attributes that survive (engine + tooltip contracts). */
    private const array ALLOWED_DATA_ATTRS = [
        'data-gterm', 'data-gc', 'data-mdx-math', 'data-mermaid-source',
        'data-mermaid-fixes', 'data-mermaid-fixes-json', 'data-mermaid-diagram',
        'data-mermaid-repair-version', 'data-copy-type', 'data-copy-source',
        'data-lang', 'data-footnote-ref', 'data-footnote-backref', 'data-align',
        'data-lightbox', 'data-alert', 'data-toc-hidden', 'data-code-title',
        'data-action',
    ];

    public function __construct(
        private readonly bool $allowRawHtml = false,
        private readonly bool $allowIframes = false,
        private readonly bool $allowExternalImages = true,
    ) {
    }

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return $html;
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        // The xml encoding declaration keeps UTF-8 intact; the wrapper div gives
        // the fragment a single root we can unwrap on the way out.
        $wrapped = '<?xml encoding="UTF-8"?><div id="mdv-sanitize-root">' . $html . '</div>';
        $loaded = $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded) {
            // Unparseable output should never happen (we produced it) — fail closed.
            return '<p>' . htmlspecialchars('Rendered content could not be sanitized and was withheld.', ENT_QUOTES) . '</p>';
        }

        $root = $doc->getElementById('mdv-sanitize-root');
        if ($root === null) {
            return '';
        }

        $this->walk($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= (string) $doc->saveHTML($child);
        }

        return $out;
    }

    private function walk(\DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node instanceof \DOMComment || $node instanceof \DOMProcessingInstruction) {
                $node->parentNode?->removeChild($node);
                continue;
            }
            if ($node instanceof \DOMText) {
                continue;
            }
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
                $node->parentNode?->removeChild($node);
                continue;
            }
            if (in_array($tag, self::EMBED_TAGS, true) && !$this->allowIframes) {
                $this->unwrap($node);
                continue;
            }
            if ($tag === 'svg' || self::isSvgDescendant($node)) {
                // Inert-graphics allowlist. Author SVG only exists here when
                // ALLOW_RAW_HTML is on; the app's own icons always pass.
                if (!in_array($tag, self::SVG_TAGS, true)) {
                    $this->unwrap($node);
                    continue;
                }
                $this->filterAttributes($node, $tag, svg: true);
                $this->walk($node);
                continue;
            }
            if (!in_array($tag, self::ALLOWED_TAGS, true)
                && !(in_array($tag, self::EMBED_TAGS, true) && $this->allowIframes)) {
                // Unknown tag: keep the children, drop the tag itself.
                $this->unwrap($node);
                continue;
            }
            if ($tag === 'input') {
                $type = strtolower($node->getAttribute('type'));
                if ($type !== 'checkbox') {
                    $node->parentNode?->removeChild($node);
                    continue;
                }
            }
            if ($tag === 'button') {
                // Only app-style control buttons survive: type=button and a
                // class from the md- namespace (anything else is author UI
                // spoofing and gets unwrapped).
                $type = strtolower($node->getAttribute('type'));
                $class = $node->getAttribute('class');
                $isAppButton = ($type === 'button' || $type === '')
                    && preg_match('/(^|\s)md-[a-z0-9-]+/i', $class) === 1;
                if (!$isAppButton) {
                    $this->unwrap($node);
                    continue;
                }
                if ($type === '') {
                    $node->setAttribute('type', 'button');
                }
            }

            $this->filterAttributes($node, $tag);
            $this->walk($node);
        }
    }

    private static function isSvgDescendant(\DOMElement $node): bool
    {
        for ($p = $node->parentNode; $p instanceof \DOMElement; $p = $p->parentNode) {
            if (strtolower($p->tagName) === 'svg') {
                return true;
            }
        }

        return false;
    }

    private function filterAttributes(\DOMElement $node, string $tag, bool $svg = false): void
    {
        $allowed = $svg ? [] : array_merge(self::GLOBAL_ATTRS, self::TAG_ATTRS[$tag] ?? []);

        foreach (iterator_to_array($node->attributes) as $attr) {
            /** @var \DOMAttr $attr */
            $name = strtolower($attr->nodeName);
            $value = $attr->nodeValue ?? '';

            // Kill every event handler and anything starting with on.
            if (str_starts_with($name, 'on')) {
                $node->removeAttribute($attr->nodeName);
                continue;
            }

            if ($name === 'style') {
                if (!$this->allowRawHtml || $this->styleIsDangerous($value)) {
                    $node->removeAttribute($attr->nodeName);
                }
                continue;
            }

            if ($name === 'id') {
                $keep = !$svg
                    && in_array($tag, self::ID_TAGS, true)
                    && preg_match('/^[A-Za-z][A-Za-z0-9_:.\-]{0,96}$/', $value) === 1;
                if (!$keep) {
                    $node->removeAttribute($attr->nodeName);
                }
                continue;
            }

            if (str_starts_with($name, 'aria-')) {
                if (preg_match('/^aria-[a-z]{1,30}$/', $name) === 1) {
                    continue; // kept (also on SVG, for a11y)
                }
                $node->removeAttribute($attr->nodeName);
                continue;
            }

            if ($name === 'class' && $tag === 'button') {
                // Buttons keep only md- namespace classes.
                $tokens = array_filter(
                    preg_split('/\s+/', $value) ?: [],
                    static fn (string $t): bool => str_starts_with($t, 'md-'),
                );
                if ($tokens === []) {
                    $node->removeAttribute($attr->nodeName);
                } else {
                    $node->setAttribute('class', implode(' ', $tokens));
                }
                continue;
            }

            if (str_starts_with($name, 'data-')) {
                if (!$svg && in_array($name, self::ALLOWED_DATA_ATTRS, true)) {
                    $this->scrubUrlishData($node, $attr, $value);
                    continue;
                }
                $node->removeAttribute($attr->nodeName);
                continue;
            }

            if ($svg) {
                // Permissive-but-safe attribute set for SVG presentation.
                if (preg_match('/^[a-z][a-z0-9:\-]{0,30}$/i', $name) === 1
                    && !in_array($name, ['href', 'xlink:href', 'src', 'from', 'to', 'by', 'values', 'attributename'], true)) {
                    continue;
                }
                $node->removeAttribute($attr->nodeName);
                continue;
            }

            if (!in_array($name, $allowed, true)) {
                $node->removeAttribute($attr->nodeName);
                continue;
            }

            // URL-bearing attributes get the scheme check.
            if (in_array($name, ['href', 'src', 'cite', 'poster', 'download'], true)) {
                if (!$this->urlIsSafe($value, $tag, $name)) {
                    $node->removeAttribute($attr->nodeName);
                    if ($name === 'src' && $tag === 'img') {
                        $this->replaceWithBlockedNote($node);
                    }
                }
                continue;
            }

            if ($name === 'target') {
                $node->setAttribute('target', strtolower($value) === '_blank' ? '_blank' : '_self');
                if ($node->getAttribute('target') === '_blank' && !$node->hasAttribute('rel')) {
                    $node->setAttribute('rel', 'noopener noreferrer');
                }
            }
        }
    }

    private function scrubUrlishData(\DOMElement $node, \DOMAttr $attr, string $value): void
    {
        // data-copy-source and friends may carry URLs — apply the same rules.
        if (str_contains($value, '://') && !$this->urlIsSafe($value, 'a', 'href')) {
            $node->removeAttribute($attr->nodeName);
        }
    }

    private function urlIsSafe(string $url, string $tag, string $attr): bool
    {
        $url = trim($url);
        if ($url === '') {
            return false;
        }
        // Strip control chars and HTML-entity-encoded tricks before the check.
        $decoded = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $decoded = (string) preg_replace('/[\x00-\x20]+/', '', $decoded);
        $lower = strtolower($decoded);

        if (str_starts_with($lower, 'javascript:') || str_starts_with($lower, 'vbscript:')
            || str_starts_with($lower, 'data:text/html') || str_starts_with($lower, 'blob:')) {
            return false;
        }
        if (str_starts_with($lower, 'data:')) {
            if ($tag === 'img' && $attr === 'src') {
                return preg_match('#^data:image/(png|jpe?g|gif|webp|avif);#i', $lower) === 1;
            }

            return false;
        }
        if (preg_match('#^[a-z][a-z0-9+.\-]*:#i', $decoded) === 1) {
            $scheme = strtolower((string) preg_replace('/:.*$/', '', $decoded));
            if (!in_array($scheme, ['http', 'https', 'mailto', 'tel'], true)) {
                return false;
            }
            if ($tag === 'img' && in_array($scheme, ['http', 'https'], true) && !$this->allowExternalImages) {
                return false;
            }
        }

        return true;
    }

    private function styleIsDangerous(string $style): bool
    {
        $lower = strtolower($style);

        return str_contains($lower, 'expression(')
            || str_contains($lower, 'behavior:')
            || str_contains($lower, '-moz-binding')
            || str_contains($lower, 'javascript:')
            || str_contains($lower, 'url(data:text/html');
    }

    private function replaceWithBlockedNote(\DOMElement $node): void
    {
        $doc = $node->ownerDocument;
        if ($doc === null) {
            return;
        }
        $span = $doc->createElement('span');
        $span->setAttribute('class', 'md-blocked-embed');
        $span->appendChild($doc->createTextNode('🖼 Image blocked by server policy'));
        $node->parentNode?->replaceChild($span, $node);
    }

    /** Remove the tag, keep (and re-parent) its children. */
    private function unwrap(\DOMElement $node): void
    {
        $parent = $node->parentNode;
        if ($parent === null) {
            return;
        }
        $doc = $node->ownerDocument;
        if ($doc === null) {
            return;
        }
        foreach (iterator_to_array($node->childNodes) as $child) {
            $parent->insertBefore($child, $node);
        }
        $parent->removeChild($node);
        // Re-walk the promoted children at the parent level.
        $this->walk($parent);
    }
}
