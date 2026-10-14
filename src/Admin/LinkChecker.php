<?php

/**
 * MD.Viewer 3.0 — link & content integrity checker.
 *
 * Scans the corpus once and reports:
 *  - broken relative .md links (target missing)
 *  - broken anchors (#target heading not present in the target document)
 *  - broken relative media references (images/files that do not exist)
 *  - orphaned documents (no inbound links; home excluded)
 */

declare(strict_types=1);

namespace MdViewer\Admin;

use MdViewer\Files\MarkdownRepository;
use MdViewer\Markdown\ExcerptService;
use MdViewer\Markdown\Extensions\HeadingProcessor;
use MdViewer\Markdown\PathUtils;

final class LinkChecker
{
    public function __construct(private readonly MarkdownRepository $repo)
    {
    }

    /**
     * @return array{
     *   documents: int,
     *   broken_links: list<array{source: string, line: int, target: string, kind: string}>,
     *   broken_media: list<array{source: string, line: int, target: string}>,
     *   orphans: list<string>,
     *   took_ms: int
     * }
     */
    public function run(int $maxDocuments = 2000): array
    {
        $started = hrtime(true);
        $files = $this->repo->scan();
        $root = $this->repo->browseDir();

        $brokenLinks = [];
        $brokenMedia = [];
        $inbound = [];
        $allPaths = [];
        foreach ($files as $file) {
            $allPaths[(string) $file['path']] = true;
        }

        $headingCache = [];

        foreach (array_slice($files, 0, $maxDocuments) as $file) {
            $path = (string) $file['path'];
            $absolute = $root . '/' . $path;
            $source = MarkdownRepository::read($absolute) ?? '';
            [, $body] = ExcerptService::extractFrontMatter($source);
            $docDir = dirname($path) === '.' ? '' : dirname($path);

            $lines = explode("\n", $body);
            $inFence = false;
            foreach ($lines as $lineNo => $line) {
                if (preg_match('/^\s{0,3}(`{3,}|~{3,})/', $line) === 1) {
                    $inFence = !$inFence;
                    continue;
                }
                if ($inFence) {
                    continue;
                }

                // Markdown links + images: [text](target) / ![alt](target)
                if (preg_match_all('/!?\[[^\]]*\]\(\s*([^)\s]+)(?:\s+"[^"]*")?\s*\)/', $line, $matches) === false) {
                    continue;
                }
                foreach ($matches[1] as $target) {
                    $target = (string) $target;
                    if (str_starts_with($target, '#')) {
                        // In-document anchor — validate against own headings
                        if (strlen($target) > 1) {
                            $anchors = $headingCache[$path] ??= $this->anchorsOf($path, $root);
                            $fragment = rawurldecode(substr($target, 1));
                            if ($fragment !== '' && !in_array($fragment, $anchors, true) && count($brokenLinks) < 500) {
                                $brokenLinks[] = ['source' => $path, 'line' => $lineNo + 1, 'target' => $target, 'kind' => 'anchor'];
                            }
                        }
                        continue;
                    }
                    if (preg_match('/^[a-z][a-z0-9+.\-]*:/i', $target) === 1 || str_starts_with($target, '/')) {
                        continue; // external or site-absolute — not our business
                    }
                    [$relTarget, $fragment] = PathUtils::splitFragment($target);
                    $resolved = PathUtils::joinRelative($docDir, rawurldecode($relTarget));
                    if ($resolved === null) {
                        $brokenLinks[] = ['source' => $path, 'line' => $lineNo + 1, 'target' => $target, 'kind' => 'escape'];
                        continue;
                    }
                    $inbound[$resolved] = true;

                    if (preg_match('/\.markdown$/i', $resolved) === 1 || preg_match('/\.md$/i', $resolved) === 1) {
                        if (!isset($allPaths[$resolved])) {
                            $brokenLinks[] = ['source' => $path, 'line' => $lineNo + 1, 'target' => $target, 'kind' => 'document'];
                            continue;
                        }
                        if ($fragment !== null && $fragment !== '') {
                            $anchors = $headingCache[$resolved] ??= $this->anchorsOf($resolved, $root);
                            if (!in_array(rawurldecode($fragment), $anchors, true) && count($brokenLinks) < 500) {
                                $brokenLinks[] = ['source' => $path, 'line' => $lineNo + 1, 'target' => $target, 'kind' => 'anchor'];
                            }
                        }
                        continue;
                    }

                    // Media reference — must exist inside the content root.
                    if (!is_file($root . '/' . $resolved)) {
                        $brokenMedia[] = ['source' => $path, 'line' => $lineNo + 1, 'target' => $target];
                    }
                }
            }
        }

        $home = trim($this->repo->homeDocument() !== null
            ? (string) ($this->repo->validator()->relativize($this->repo->homeDocument(), $root) ?? '')
            : '');
        $orphans = [];
        foreach (array_keys($allPaths) as $path) {
            if ($path === $home) {
                continue;
            }
            if (!isset($inbound[$path])) {
                $orphans[] = (string) $path;
            }
        }
        sort($orphans);

        return [
            'documents' => count($files),
            'broken_links' => $brokenLinks,
            'broken_media' => $brokenMedia,
            'orphans' => $orphans,
            'took_ms' => (int) ((hrtime(true) - $started) / 1_000_000),
        ];
    }

    /** @return list<string> heading anchors of one document */
    private function anchorsOf(string $relative, string $root): array
    {
        $source = MarkdownRepository::read($root . '/' . $relative) ?? '';
        [, $body] = ExcerptService::extractFrontMatter($source);
        $anchors = [];
        foreach (ExcerptService::headings($body) as $heading) {
            $anchors[] = HeadingProcessor::slugify((string) $heading['text']);
        }

        return $anchors;
    }
}
