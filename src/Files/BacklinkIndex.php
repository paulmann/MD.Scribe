<?php

/**
 * MD.Scribe 3.3 — backlink index.
 *
 * Inverted wikilink map: target document → the documents that mention it.
 * Stored as one JSON file under storage/index/ and refreshed lazily: every read
 * compares the stored corpus stamp (paths + mtime + size) with the live one, so
 * a document added by git, FTP or the editor is picked up without a manual
 * rebuild. Rebuilding is also an explicit admin action.
 */

declare(strict_types=1);

namespace MdViewer\Files;

use MdViewer\Core\Logger;
use MdViewer\Core\Paths;
use MdViewer\Markdown\ExcerptService;

final class BacklinkIndex
{
    private const int VERSION = 1;

    /** One target never keeps more than this many sources (bounds the file). */
    private const int MAX_SOURCES_PER_TARGET = 500;

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    private string $stamp = '';

    public function __construct(
        private readonly MarkdownRepository $repository,
        private readonly WikilinkResolver $resolver,
        private readonly Logger $logger = new Logger(),
        private readonly string $dir = '',
    ) {
    }

    /**
     * Documents that link to $relativePath.
     *
     * @return list<array{from: string, title: string, anchor: ?string}>
     */
    public function forDocument(string $relativePath): array
    {
        $data = $this->load();
        $key = self::normalizePath($relativePath);
        $links = $data['links'] ?? [];
        if (!is_array($links) || !isset($links[$key]) || !is_array($links[$key])) {
            return [];
        }

        /** @var list<array{from: string, title: string, anchor: ?string}> $list */
        $list = array_values($links[$key]);

        return $list;
    }

    /** Corpus stamp the loaded index describes. */
    public function stamp(): string
    {
        $this->load();

        return $this->stamp;
    }

    /** @return array{documents: int, targets: int, links: int, stamp: string} */
    public function rebuild(): array
    {
        $stamp = CorpusStamp::of($this->repository);
        $data = $this->build($stamp);
        $this->write($data);
        $this->loaded = $data;
        $this->stamp = $stamp;

        $this->logger->info('backlink index rebuilt', [
            'documents' => $data['documents'],
            'targets' => count($data['links']),
            'links' => $data['links_total'],
        ]);

        return [
            'documents' => (int) $data['documents'],
            'targets' => count($data['links']),
            'links' => (int) $data['links_total'],
            'stamp' => $stamp,
        ];
    }

    /** @return array{documents: int, targets: int, links: int, built: int, stamp: string} */
    public function stats(): array
    {
        $data = $this->load();
        $links = is_array($data['links'] ?? null) ? $data['links'] : [];

        return [
            'documents' => (int) ($data['documents'] ?? 0),
            'targets' => count($links),
            'links' => (int) ($data['links_total'] ?? 0),
            'built' => (int) ($data['built'] ?? 0),
            'stamp' => $this->stamp,
        ];
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
     * @return array{version: int, built: int, stamp: string, documents: int, links_total: int, links: array<string, list<array{from: string, title: string, anchor: ?string}>>}
     */
    private function build(string $stamp): array
    {
        $links = [];
        $documents = 0;
        $total = 0;

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

            [, $body] = ExcerptService::extractFrontMatter($source);
            $docDir = dirname($relative) === '.' ? '' : dirname($relative);
            $seen = [];
            foreach (WikilinkResolver::extract($body) as $link) {
                $resolved = $this->resolver->resolve($link['target'], $docDir)
                    ?? (WikilinkResolver::candidates($link['target'], $docDir)[0] ?? null);
                if ($resolved === null || $resolved === $relative || isset($seen[$resolved])) {
                    continue;
                }
                $seen[$resolved] = true;
                if (count($links[$resolved] ?? []) >= self::MAX_SOURCES_PER_TARGET) {
                    continue;
                }
                $links[$resolved][] = [
                    'from' => $relative,
                    'title' => (string) ($document['title'] ?? $relative),
                    'anchor' => $link['anchor'],
                ];
                $total++;
            }
        }

        ksort($links);

        return [
            'version' => self::VERSION,
            'built' => time(),
            'stamp' => $stamp,
            'documents' => $documents,
            'links_total' => $total,
            'links' => $links,
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
            $this->logger->warning('backlink index: storage not writable', ['dir' => $dir]);

            return;
        }

        $payload = (string) json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
        if (@file_put_contents($tmp, $payload, LOCK_EX) === false) {
            $this->logger->warning('backlink index: write failed', ['file' => $file]);

            return;
        }
        if (!@rename($tmp, $file)) {
            @unlink($tmp);
            $this->logger->warning('backlink index: rename failed', ['file' => $file]);
        }
    }

    private function file(): string
    {
        $dir = $this->dir !== '' ? $this->dir : Paths::index();

        return rtrim($dir, '/\\') . '/backlinks.json';
    }

    private static function normalizePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        while (str_starts_with($path, './')) {
            $path = substr($path, 2);
        }

        return ltrim($path, '/');
    }
}
