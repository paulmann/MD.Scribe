<?php

/**
 * MD.Viewer 3.0 — fenced code block renderer.
 *
 *  - ```mermaid / ```mmd  → repaired diagram block (server-side auto-repair,
 *    diagnostics carried in data attributes, rendered lazily by the client);
 *  - anything else        → syntax-highlighted block with a language/title
 *    bar, copy button and wrap toggle (server-side highlight, zero JS cost).
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use MdViewer\Markdown\CodeHighlighter;
use MdViewer\Markdown\ContextHolder;
use MdViewer\Markdown\MermaidNormalizer;
use MdViewer\View\Icons;

final class CodeBlockRenderer implements NodeRendererInterface
{
    public function __construct(private readonly ContextHolder $holder)
    {
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        if ($node instanceof FencedCode) {
            $info = (string) ($node->getInfo() ?? '');
            $code = $node->getLiteral();
        } elseif ($node instanceof IndentedCode) {
            $info = '';
            $code = $node->getLiteral();
        } else {
            return '';
        }

        $code = rtrim($code, "\n");
        $lang = strtolower((string) (preg_split('/\s+/', trim($info))[0] ?? ''));

        if (MermaidNormalizer::isMermaidLanguage($lang) && $this->feature('FEATURE_MERMAID')) {
            return $this->renderMermaid($code);
        }

        return $this->renderCode($code, $info, $lang);
    }

    private function renderMermaid(string $code): string
    {
        $repair = MermaidNormalizer::repair($code);
        $ctx = $this->holder->current;
        if ($ctx !== null) {
            $ctx->hasMermaid = true;
        }

        $fixesJson = htmlspecialchars(
            (string) json_encode($repair['fixes'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            ENT_QUOTES,
            'UTF-8',
        );
        $original = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        $repaired = htmlspecialchars($repair['source'], ENT_NOQUOTES, 'UTF-8');

        return '<div class="md-mermaid" data-mermaid-diagram="' . htmlspecialchars($repair['diagramType'], ENT_QUOTES) . '">'
            . '<pre class="mermaid" data-mermaid-source="' . $original . '"'
            . ' data-mermaid-fixes="' . count($repair['fixes']) . '"'
            . ' data-mermaid-fixes-json="' . $fixesJson . '"'
            . ' data-mermaid-repair-version="' . MermaidNormalizer::REPAIR_VERSION . '">'
            . $repaired
            . '</pre>'
            . '<div class="md-mermaid-bar">'
            . '<button type="button" class="md-copy-btn" data-copy-type="mermaid" aria-label="Copy diagram source" title="Copy diagram source">'
            . Icons::svg('copy', 'h-3.5 w-3.5') . '<span>Copy source</span></button>'
            . '</div>'
            . '</div>';
    }

    private function renderCode(string $code, string $info, string $lang): string
    {
        // title="…" in the info string becomes the bar caption
        $title = null;
        if (preg_match('/title\s*=\s*"([^"]*)"/', $info, $m) === 1) {
            $title = $m[1];
        } elseif (preg_match("/title\s*=\s*'([^']*)'/", $info, $m) === 1) {
            $title = $m[1];
        }

        $highlighted = CodeHighlighter::highlight($code, $lang);
        $label = $title !== null && $title !== ''
            ? htmlspecialchars($title, ENT_QUOTES)
            : htmlspecialchars(CodeHighlighter::label($lang), ENT_QUOTES);
        $langClass = $highlighted['language'] !== '' ? ' language-' . htmlspecialchars($highlighted['language'], ENT_QUOTES) : '';

        // Embed / GitHub-style mode: emit portable markup only — no toolbar,
        // no icons, no wrapper. An external stylesheet (github-markdown-css,
        // highlight.js themes) styles it exactly like a README on GitHub.
        if ($this->holder->current?->plainCodeBlocks === true) {
            return '<pre><code class="hljs' . $langClass . '">' . $highlighted['html'] . '</code></pre>';
        }

        return '<div class="md-code" data-lang="' . htmlspecialchars($lang, ENT_QUOTES) . '"'
            . ($title !== null ? ' data-code-title="' . htmlspecialchars($title, ENT_QUOTES) . '"' : '') . '>'
            . '<div class="md-code-bar">'
            . '<span class="md-code-lang">' . Icons::svg('code', 'h-3.5 w-3.5 mr-1.5 inline-block align-[-2px]') . $label . '</span>'
            . '<span class="md-code-actions">'
            . '<button type="button" class="md-icon-btn md-wrap-btn" data-action="wrap" aria-label="Toggle word wrap" title="Toggle word wrap">'
            . Icons::svg('wrap-text', 'h-3.5 w-3.5') . '</button>'
            . '<button type="button" class="md-copy-btn" data-copy-type="code" aria-label="Copy code" title="Copy code">'
            . Icons::svg('copy', 'h-3.5 w-3.5') . '<span>Copy</span></button>'
            . '</span>'
            . '</div>'
            . '<pre class="md-code-pre"><code class="hljs' . $langClass . '">' . $highlighted['html'] . '</code></pre>'
            . '</div>';
    }

    private function feature(string $key): bool
    {
        return $this->holder->current?->feature($key) ?? true;
    }
}
