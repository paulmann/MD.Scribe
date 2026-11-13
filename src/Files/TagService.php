<?php

/**
 * MD.Scribe 3.3 — tag index.
 *
 * Tags come from the document frontmatter (`tags: [a, b]` or a block list) and
 * are the same values the viewer already prints under the title. The index is a
 * tag → documents map under storage/index/, refreshed lazily with the same
 * corpus stamp as the backlink index, so new and renamed documents appear
 * without a rebuild.
 */

declare(strict_types=1);

namespace MdViewer\Files;

use MdViewer\Core\Logger;
use MdViewer\Core\Paths;
use MdViewer\Markdown\ExcerptService;

final class TagService
{
    private const int VERSION = 1;

    private const int MAX_TAGS_PER_DOCUMENT = 32;

    private const int MAX_TAG_LENGTH = 40;

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    private string $stamp = '';

    public function __construct(
        private readonly MarkdownRepository $repository,
        private readonly Logger $logger = new Logger(),
        private readonly string $dir = '',
    ) {
    }

    /**
     * Every tag with the documents that carry it.
     *
     * @return array<string, list<array{path: string, title: string}>>
     */
    public function all(): array
    {
        $data = $this->load();
        $tags = $data['tags'] ?? [];
        if (!is_array($tags)) {
            return [];
        }

        /** @var array<string, list<array{path: string, title: string}>> $tags */
        return $tags;
    }

    /**
     * Documents for one tag (case-insensitive, as typed by the visitor).
     *
     * @return list<array{path: string, title: string}>
     */
    public function documents(string $tag): array
    {
        $normalized = self::normalizeTag($tag);
        if ($normalized === null) {
            return [];
        }
        $tags = $this->all();

        return $tags[$normalized] ?? [];
    }

    /** @return array{tags: int, documents: int, stamp: string} */
    public function rebuild(): array
    {
        $stamp = CorpusStamp::of($this->repository);
        $data = $this->build($stamp);
        $this->write($data);
        $this->loaded = $data;
        $this->stamp = $stamp;

        $this->logger->info('tag index rebuilt', [
            'tags' => count($data['tags']),
            'documents' => $data['documents'],
        ]);

        return [
            'tags' => count($data['tags']),
            'documents' => (int) $data['documents'],
            'stamp' => $stamp,
        ];
    }

    /** @return array{tags: int, documents: int, built: int, stamp: string} */
    public function stats(): array
    {
        $data = $this->load();
        $tags = is_array($data['tags'] ?? null) ? $data['tags'] : [];

        return [
            'tags' => count($tags),
            'documents' => (int) ($data['documents'] ?? 0),
            'built' => (int) ($data['built'] ?? 0),
            'stamp' => $this->stamp,
        ];
    }

    /**
     * Tags declared in a frontmatter array (already parsed by ExcerptService).
     *
     * @param array<string, mixed> $frontMatter
     * @return list<string>
     */
    public static function fromFrontMatter(array $frontMatter): array
    {
        $raw = $frontMatter['tags'] ?? [];
        if (is_string($raw)) {
            $raw = explode(',', $raw);
        }
        if (!is_array($raw)) {
            return [];
        }

        $tags = [];
        foreach ($raw as $value) {
            $tag = self::normalizeTag((string) $value);
            if ($tag !== null && !in_array($tag, $tags, true)) {
                $tags[] = $tag;
            }
            if (count($tags) >= self::MAX_TAGS_PER_DOCUMENT) {
                break;
            }
        }

        return $tags;
    }

    /** Lower-case, dash-separated, no leading `#`, bounded length. */
    public static function normalizeTag(string $raw): ?string
    {
        $tag = trim($raw);
        $tag = ltrim($tag, '#');
        $tag = mb_strtolower($tag, 'UTF-8');
        $tag = (string) preg_replace('/\s+/u', '-', $tag);
        $tag = (string) preg_replace('/[^\p{L}\p{N}_\-.]+/u', '', $tag);
        $tag = trim($tag, '-.');
        if ($tag === '') {
            return null;
        }

        return mb_substr($tag, 0, self::MAX_TAG_LENGTH, 'UTF-8');
    }

    // ── Internals ──────────────────────────────────────────────────────────

    /** @return array<string, mixed> */
    private function load(): array
    {
        if ($this->loaded !== null) {
            return $this->loaded;
        }

        $stamp = CorpusStamp::of($this->repository);
        $this->stamp = $stamp;

        $stored = $this->read();
        if ($stored === null || ($stored['stamp'] ?? '') !== $stamp) {
            $stored = $this->build($stamp);
            $this->write($stored);
        }

        return $this->loaded = $stored;
    }

    /**
     * @return array{version: int, built: int, stamp: string, documents: int, tags: array<string, list<array{path: string, title: string}>>}
     */
    private function build(string $stamp): array
    {
        $tags = [];
        $documents = 0;

        foreach ($this->repository->scan() as $document) {
            $relative = (string) $document['path'];
            $absolute = $this->repository->resolve($relative);
            if ($absolute === null) {
                continue;
            }
            $source = MarkdownRepository::read($absolute);
            if ($source === null) {
                continue;
            }
            $documents++;

            [$frontMatter] = ExcerptService::extractFrontMatter($source);
            foreach (self::fromFrontMatter($frontMatter) as $tag) {
                $tags[$tag][] = [
                    'path' => $relative,
                    'title' => (string) ($document['title'] ?? $relative),
                ];
            }
        }

        ksort($tags);
        foreach ($tags as $tag => $entries) {
            usort($entries, static fn (array $a, array $b): int => strnatcasecmp($a['path'], $b['path']));
            $tags[$tag] = $entries;
        }

        return [
            'version' => self::VERSION,
            'built' => time(),
            'stamp' => $stamp,
            'documents' => $documents,
            'tags' => $tags,
        ];
    }

    /** @return array<string, mixed>|null */
    private function read(): ?array
    {
        $file = $this->file();
        if (!is_file($file)) {
            return null;
        }
        $raw = @file_get_contents($file);
        if (!is_string($raw) || !json_validate($raw)) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data) || (int) ($data['version'] ?? 0) !== self::VERSION) {
            return null;
        }

        return $data;
    }

    /** @param array<string, mixed> $data */
    private function write(array $data): void
    {
        $file = $this->file();
        $dir = dirname($file);
        if (!is_dir($dir) && !@mkdir($dir, 0o755, true) && !is_dir($dir)) {
            $this->logger->warning('tag index: storage not writable', ['dir' => $dir]);

            return;
        }

        $payload = (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
            $this->logger->warning('tag index: write failed', ['file' => $file]);

            return;
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            $this->logger->warning('tag index: rename failed', ['file' => $file]);
        }
    }

    private function file(): string
    {
        $dir = $this->dir !== '' ? $this->dir : Paths::index();

        return rtrim($dir, '/\\') . '/tags.json';
    }
}
