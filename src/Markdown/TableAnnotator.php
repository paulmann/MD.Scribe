<?php

/**
 * MD.Viewer 3.0 — glossary table annotator (DOM pass).
 *
 * Gives every rendered table the stable identifiers the tooltip engine
 * expects (the v2.4 contract, kept byte-for-byte):
 *
 *   <table id="tbl-N">            N counts ALL tables in document order
 *     first-column <td data-gc="1">
 *     every body cell   id="gd-tbl-N-{row}-{col}"   (row 0-based, col 1-based)
 *
 * Runs after sanitisation and before tooltip injection.
 */

declare(strict_types=1);

namespace MdViewer\Markdown;

final class TableAnnotator
{
    public static function annotate(string $html): string
    {
        if (!str_contains($html, '<table')) {
            return $html;
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $loaded = $doc->loadHTML(
            '<?xml encoding="UTF-8"?><div id="mdv-annotate-root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET,
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            return $html;
        }

        $root = $doc->getElementById('mdv-annotate-root');
        if ($root === null) {
            return $html;
        }

        $xpath = new \DOMXPath($doc);
        $tables = $xpath->query('.//table', $root);
        $n = 0;
        foreach ($tables ?: [] as $table) {
            if (!$table instanceof \DOMElement) {
                continue;
            }
            $n++;
            $slug = 'tbl-' . $n;
            $table->setAttribute('id', $slug);

            $rowIndex = 0;
            foreach ($xpath->query('./tbody/tr | ./tr', $table) ?: [] as $tr) {
                if (!$tr instanceof \DOMElement) {
                    continue;
                }
                $col = 0;
                foreach ($tr->childNodes as $cell) {
                    if (!$cell instanceof \DOMElement || !in_array(strtolower($cell->tagName), ['td', 'th'], true)) {
                        continue;
                    }
                    $col++;
                    $cell->setAttribute('id', 'gd-' . $slug . '-' . $rowIndex . '-' . $col);
                    if ($col === 1) {
                        $cell->setAttribute('data-gc', '1');
                    }
                }
                $rowIndex++;
            }
        }

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= (string) $doc->saveHTML($child);
        }

        return $out;
    }
}
