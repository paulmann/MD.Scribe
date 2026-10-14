<?php

/**
 * MD.Viewer 3.0 — server-side search index.
 *
 * A pure-PHP inverted index over the document corpus, persisted as JSON in
 * storage/index/. No database, no external services.
 *
 * Strategy: index titles, headings and a capped set of body terms per
 * document; generate snippets lazily from the live file for the top results
 * only, so the index stays small even for large corpora.
 *
 * Staleness: the index records the corpus fingerprint (file count + newest
 * mtime + total bytes); a search request against a stale index triggers a
 * rebuild first. Admins can force a rebuild from the dashboard.
 */

declare(strict_types=1);

namespace MdViewer\Files;

use MdViewer\Core\Logger;
use MdViewer\Markdown\ExcerptService;

final class SearchIndex
{
    private const int INDEX_VERSION = 1;
    private const int MAX_TERMS_PER_DOC = 400;
    private const int SNIPPET_RADIUS = 110;

    /** @var array<string, mixed>|null */
    private ?array $index = null;

    public function __construct(
        private readonly MarkdownRepository $repo,
        private readonly Logger $logger = new Logger(),
        private readonly string $dir = '',
    ) {
    }

    private function file(): string
    {
        $dir = $this->dir !== '' ? $this->dir : \MdViewer\Core\Paths::index();
        if (!is_dir($dir)) {
            @mkdir($dir, 0o755, true);
        }

        return $dir . '/search-index.json';
    }

    // ── Public API ─────────────────────────────────────────────────────────

    /**
     * Search the corpus.
     *
     * @return array{query: string, took_ms: int, total: int, results: list<array{path: string, title: string, snippet: string, score: int, modified: int}>}
     */
    public function search(string $query, int $limit = 50): array
    {
        $started = hrtime(true);
        $this->ensureFresh();
        $index = $this->index ?? [];
        $docs = $index['docs'] ?? [];
        $inverted = $index['inverted'] ?? [];

        $terms = self::tokenize($query);
        if ($terms === []) {
            return ['query' => $query, 'took_ms' => 0, 'total' => 0, 'results' => []];
        }

        $scores = [];
        foreach ($terms as $term) {
            foreach ($inverted[$term] ?? [] as $docId => $weight) {
                $scores[$docId] = ($scores[$docId] ?? 0) + (int) $weight;
            }
        }
        // AND-preference: documents matching every term rank above partial ones.
        $termCount = count($terms);
        foreach ($scores as $docId => $score) {
            $matches = 0;
            foreach ($terms as $term) {
                if (isset($inverted[$term][$docId])) {
                    $matches++;
                }
            }
            if ($matches === $termCount && $termCount > 1) {
                $scores[$docId] = (int) $score * 3;
            } elseif ($matches < $termCount) {
                $scores[$docId] = (int) ((int) $score * $matches / $termCount);
            }
        }
        arsort($scores);

        $results = [];
        $total = count($scores);
        foreach (array_slice(array_keys($scores), 0, max(1, $limit)) as $docId) {
            $doc = $docs[$docId] ?? null;
            if ($doc === null) {
                continue;
            }
            $snippet = count($results) < 10
                ? $this->snippet((string) $doc['path'], $terms)
                : (string) ($doc['excerpt'] ?? '');
            $results[] = [
                'path' => (string) $doc['path'],
                'title' => (string) $doc['title'],
                'snippet' => $snippet,
                'score' => (int) $scores[$docId],
                'modified' => (int) ($doc['modified'] ?? 0),
            ];
        }

        return [
            'query' => $query,
            'took_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
            'total' => $total,
            'results' => $results,
        ];
    }

    /**
     * Lightweight typeahead for the command palette: titles + headings.
     *
     * @return list<array{path: string, title: string, heading: ?string, anchor: ?string, kind: string}>
     */
    public function suggest(string $query, int $limit = 12): array
    {
        $this->ensureFresh();
        $q = mb_strtolower(trim($query));
        if ($q === '') {
            return [];
        }
        $out = [];
        foreach ($this->index['docs'] ?? [] as $doc) {
            $title = (string) ($doc['title'] ?? '');
            if (mb_stripos($title, $q) !== false) {
                $out[] = ['path' => (string) $doc['path'], 'title' => $title, 'heading' => null, 'anchor' => null, 'kind' => 'doc'];
            }
            foreach ((array) ($doc['headings'] ?? []) as $heading) {
                $text = (string) ($heading['text'] ?? '');
                if ($text !== '' && mb_stripos($text, $q) !== false) {
                    $out[] = [
                        'path' => (string) $doc['path'],
                        'title' => $title,
                        'heading' => $text,
                        'anchor' => (string) ($heading['anchor'] ?? ''),
                        'kind' => 'heading',
                    ];
                }
            }
            if (count($out) >= $limit * 3) {
                break;
            }
        }

        return array_slice($out, 0, $limit);
    }

    public function stats(): array
    {
        $this->load();
        $index = $this->index ?? [];

        return [
            'documents' => count($index['docs'] ?? []),
            'terms' => count($index['inverted'] ?? []),
            'built_at' => $index['built_at'] ?? null,
            'size_bytes' => is_file($this->file()) ? (int) @filesize($this->file()) : 0,
            'fresh' => $this->isFresh(),
        ];
    }

    public function rebuild(): array
    {
        $started = hrtime(true);
        $files = $this->repo->scan();
        $docs = [];
        $inverted = [];
        $id = 0;

        foreach ($files as $file) {
            $absolute = $this->repo->browseDir() . '/' . $file['path'];
            $markdown = MarkdownRepository::read($absolute) ?? '';
            [$frontMatter, $body] = ExcerptService::extractFrontMatter($markdown);
            $title = is_string($frontMatter['title'] ?? null) && $frontMatter['title'] !== ''
                ? (string) $frontMatter['title']
                : ($file['title'] ?: $file['path']);

            // Headings with anchors
            $headings = $this->extractHeadings($body);

            // Plain text for terms (code fences stripped)
            $plain = $this->plainText($body);

            $excerpt = mb_substr(preg_replace('/\s+/u', ' ', $plain) ?? $plain, 0, 220);

            $docId = (string) $id++;
            $docs[$docId] = [
                'path' => $file['path'],
                'title' => $title,
                'headings' => $headings,
                'excerpt' => $excerpt,
                'modified' => $file['modified'],
                'size' => $file['size'],
            ];

            // Weighted terms: title > headings > body
            $weights = [];
            foreach (self::tokenize($title) as $term) {
                $weights[$term] = ($weights[$term] ?? 0) + 100;
            }
            foreach ($headings as $heading) {
                foreach (self::tokenize((string) $heading['text']) as $term) {
                    $weights[$term] = ($weights[$term] ?? 0) + 30;
                }
            }
            foreach (self::tokenize($plain) as $term) {
                $weights[$term] = ($weights[$term] ?? 0) + 5;
            }
            arsort($weights);
            foreach (array_slice($weights, 0, self::MAX_TERMS_PER_DOC, true) as $term => $weight) {
                $inverted[$term][$docId] = min(255, (int) $weight);
            }
        }

        $this->index = [
            'version' => self::INDEX_VERSION,
            'built_at' => date('c'),
            'fingerprint' => $this->fingerprint($files),
            'docs' => $docs,
            'inverted' => $inverted,
        ];
        $this->save();

        return [
            'documents' => count($docs),
            'terms' => count($inverted),
            'took_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ];
    }

    public function delete(): void
    {
        $this->index = null;
        @unlink($this->file());
    }

    // ── Internals ──────────────────────────────────────────────────────────

    private function load(): void
    {
        if ($this->index !== null) {
            return;
        }
        $path = $this->file();
        if (is_file($path)) {
            $raw = (string) @file_get_contents($path);
            if (json_validate($raw)) {
                $decoded = json_decode($raw, true);
                if (is_array($decoded) && ($decoded['version'] ?? 0) === self::INDEX_VERSION) {
                    $this->index = $decoded;

                    return;
                }
            }
        }
        $this->index = ['version' => self::INDEX_VERSION, 'docs' => [], 'inverted' => []];
    }

    private function save(): void
    {
        $dir = dirname($this->file());
        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            $this->logger->warning('search index: cannot create dir', ['dir' => $dir]);

            return;
        }
        $tmp = $this->file() . '.tmp';
        $json = (string) json_encode($this->index, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (@file_put_contents($tmp, $json, LOCK_EX) !== false) {
            @rename($tmp, $this->file());
        }
    }

    /** @param list<array{path: string, size: int, modified: int}> $files */
    private function fingerprint(array $files): string
    {
        $newest = $files === [] ? 0 : max(array_column($files, 'modified'));
        $bytes = array_sum(array_column($files, 'size'));

        return count($files) . ':' . $newest . ':' . $bytes;
    }

    private function isFresh(): bool
    {
        $this->load();
        $expected = $this->fingerprint($this->repo->scan());

        return ($this->index['fingerprint'] ?? null) === $expected;
    }

    private function ensureFresh(): void
    {
        $this->load();
        if (!$this->isFresh()) {
            $this->rebuild();
        }
    }

    /**
     * Tokenizer: unicode-aware lowercased words, length ≥ 2, digits kept,
     * CJK bigrams so substring languages remain searchable.
     *
     * @return list<string>
     */
    public static function tokenize(string $text): array
    {
        $text = mb_strtolower($text, 'UTF-8');
        $tokens = [];
        if (preg_match_all('/[\p{L}\p{N}][\p{L}\p{N}\p{M}_-]*/u', $text, $m) !== false) {
            foreach ($m[0] as $word) {
                if (preg_match('/^[\x{4E00}-\x{9FFF}\x{3040}-\x{30FF}\x{AC00}-\x{D7AF}]+$/u', $word) === 1) {
                    // CJK: index unigrams and bigrams
                    $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];
                    foreach ($chars as $char) {
                        $tokens[] = $char;
                    }
                    $count = count($chars);
                    for ($i = 0; $i + 1 < $count; $i++) {
                        $tokens[] = $chars[$i] . $chars[$i + 1];
                    }
                    continue;
                }
                if (mb_strlen($word) >= 2) {
                    $tokens[] = mb_substr($word, 0, 40);
                }
            }
        }

        return array_values(array_unique($tokens));
    }

    /** Plain text of markdown: fences, html, inline markup removed. */
    private function plainText(string $markdown): string
    {
        $markdown = preg_replace('/^\s{0,3}(`{3,}|~{3,}).*?^\s{0,3}\1\s*$/ms', ' ', $markdown) ?? $markdown;
        $markdown = preg_replace('/<[^>]+>/', ' ', $markdown) ?? $markdown;
        $markdown = preg_replace('/!\[([^\]]*)\]\([^)]*\)/', ' $1 ', $markdown) ?? $markdown;
        $markdown = preg_replace('/\[([^\]]*)\]\([^)]*\)/', ' $1 ', $markdown) ?? $markdown;
        $markdown = preg_replace('/^\s{0,3}#{1,6}\s+/m', ' ', $markdown) ?? $markdown;
        $markdown = preg_replace('/[*_~`=|#>-]+/', ' ', $markdown) ?? $markdown;

        return preg_replace('/\s+/u', ' ', $markdown) ?? $markdown;
    }

    /** @return list<array{text: string, level: int, anchor: string}> */
    private function extractHeadings(string $markdown): array
    {
        $headings = [];
        $inFence = false;
        $seen = [];
        foreach (explode("\n", $markdown) as $line) {
            if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line) === 1) {
                $inFence = !$inFence;
                continue;
            }
            if ($inFence) {
                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.+?)\s*#*\s*$/', $line, $m) === 1) {
                $text = trim(ExcerptService::stripInlineMarkdown($m[2]));
                $anchor = self::slugify($text);
                if (isset($seen[$anchor])) {
                    $anchor .= '-' . ++$seen[$anchor];
                } else {
                    $seen[$anchor] = 0;
                }
                $headings[] = ['text' => $text, 'level' => strlen($m[1]), 'anchor' => $anchor];
            }
        }

        return $headings;
    }

    /** Same slug rules as the renderer (GitHub-style). */
    public static function slugify(string $text): string
    {
        $text = mb_strtolower(trim($text), 'UTF-8');
        $text = preg_replace('/[^\p{L}\p{N}\s_-]/u', '', $text) ?? $text;
        $text = preg_replace('/[\s_]+/u', '-', $text) ?? $text;

        return trim((string) $text, '-');
    }

    /** Build a highlighted snippet from the live document. */
    private function snippet(string $relative, array $terms): string
    {
        $absolute = $this->repo->browseDir() . '/' . $relative;
        $markdown = MarkdownRepository::read($absolute) ?? '';
        [, $body] = ExcerptService::extractFrontMatter($markdown);
        $plain = trim($this->plainText($body));
        if ($plain === '') {
            return '';
        }
        $lower = mb_strtolower($plain, 'UTF-8');
        $pos = false;
        foreach ($terms as $term) {
            $found = mb_strpos($lower, $term);
            if ($found !== false && ($pos === false || $found < $pos)) {
                $pos = $found;
            }
        }
        if ($pos === false) {
            return mb_substr($plain, 0, self::SNIPPET_RADIUS * 2) . '…';
        }
        $start = max(0, $pos - self::SNIPPET_RADIUS);
        $snippet = mb_substr($plain, $start, self::SNIPPET_RADIUS * 2 + mb_strlen((string) ($terms[0] ?? '')));
        $snippet = ($start > 0 ? '…' : '') . trim($snippet) . '…';

        // Highlight matches (escape first, then wrap)
        $snippet = htmlspecialchars($snippet, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        foreach ($terms as $term) {
            $escapedTerm = preg_quote(htmlspecialchars((string) $term, ENT_QUOTES), '/');
            $snippet = (string) preg_replace('/(' . $escapedTerm . ')/iu', '<mark>$1</mark>', $snippet);
        }

        return $snippet;
    }
}
