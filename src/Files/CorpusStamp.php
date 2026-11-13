<?php

/**
 * MD.Scribe 3.3 — corpus stamp.
 *
 * A short hash of every document path with its mtime and size. Wikilink
 * existence (and therefore rendered HTML) depends on the *set* of documents,
 * not only on the file being rendered, so the stamp joins the render-cache key:
 * adding, removing or renaming a document invalidates cached links, while
 * editing one document does not invalidate every other page.
 */

declare(strict_types=1);

namespace MdViewer\Files;

final class CorpusStamp
{
    /**
     * @param list<array{path: string, modified: int, size: int}> $files scan() output (extra keys are ignored)
     */
    public static function fromScan(array $files): string
    {
        $parts = [];
        foreach ($files as $file) {
            $parts[] = $file['path'] . '|' . $file['modified'] . '|' . $file['size'];
        }
        sort($parts);

        return substr(hash('sha256', implode("\n", $parts)), 0, 16);
    }

    public static function of(MarkdownRepository $repository): string
    {
        return self::fromScan($repository->scan());
    }
}
