<?php

/**
 * MD.Scribe 3.4 — document templates.
 *
 * Five built-in skeletons an editor can start from instead of an empty file.
 * They are plain strings, not files on disk: nothing to install, nothing to
 * keep in sync with the corpus, and a template can never be edited by accident
 * through the file manager.
 *
 * Placeholders: {{title}}, {{date}} (ISO), {{slug}}.
 */

declare(strict_types=1);

namespace MdViewer\Files;

final class DocumentTemplates
{
    public const int MAX_TITLE_LENGTH = 200;

    /**
     * @return array<string, array{label: string, description: string, content: string}>
     */
    public static function all(): array
    {
        return [
            'blank' => [
                'label' => 'Blank document',
                'description' => 'Title and one heading, nothing else.',
                'content' => "# {{title}}\n\n",
            ],
            'adr' => [
                'label' => 'Architecture decision (ADR)',
                'description' => 'Context, decision, consequences — the record a design argument leaves behind.',
                'content' => <<<'MD'
---
title: {{title}}
date: {{date}}
tags: [adr]
---

# {{title}}

## Status

Proposed

## Context

What is the situation that forces a decision? Constraints, forces, what we know
and what we do not.

## Decision

What we decided, stated in one paragraph, in the present tense.

## Consequences

- What becomes easier.
- What becomes harder.
- What we will have to revisit, and when.
MD,
            ],
            'meeting' => [
                'label' => 'Meeting notes',
                'description' => 'Attendees, decisions and action items with owners.',
                'content' => <<<'MD'
---
title: {{title}}
date: {{date}}
tags: [meeting]
---

# {{title}}

**Date:** {{date}}
**Attendees:**

## Agenda

1.

## Notes

## Decisions

-

## Actions

| Owner | Action | Due |
| --- | --- | --- |
|  |  |  |
MD,
            ],
            'howto' => [
                'label' => 'How-to guide',
                'description' => 'Prerequisites, numbered steps, a verification step and a rollback.',
                'content' => <<<'MD'
---
title: {{title}}
date: {{date}}
tags: [howto]
---

# {{title}}

One sentence: what this guide gets you, and when you need it.

## Before you start

- Prerequisite one
- Prerequisite two

## Steps

1. First step.
2. Second step.

## Verify

How the reader proves it worked — a command with its expected output, or a
visible result.

## Roll back

How to undo the change if it goes wrong.

## Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
|  |  |  |
MD,
            ],
            'reference' => [
                'label' => 'Reference page',
                'description' => 'Overview, parameters, errors and examples for one component.',
                'content' => <<<'MD'
---
title: {{title}}
date: {{date}}
tags: [reference]
---

# {{title}}

What this component is and what it is responsible for. One paragraph, no
motivation — that belongs in the guide.

## Parameters

| Name | Type | Default | Meaning |
| --- | --- | --- | --- |
|  |  |  |  |

## Returns

## Errors

| Code | Meaning | What to do |
| --- | --- | --- |
|  |  |  |

## Examples

```bash
# minimal working example
```
MD,
            ],
        ];
    }

    /** @return array{label: string, description: string, content: string}|null */
    public static function get(string $id): ?array
    {
        return self::all()[$id] ?? null;
    }

    /** Filled-in content, or null when the template id is unknown. */
    public static function render(string $id, string $title): ?string
    {
        $template = self::get($id);
        if ($template === null) {
            return null;
        }

        return strtr($template['content'], [
            '{{title}}' => $title,
            '{{date}}' => date('Y-m-d'),
            '{{slug}}' => self::slug($title),
        ]);
    }

    /**
     * File-name-safe slug: letters (any script), digits, dashes and underscores.
     * A title made only of punctuation falls back to "untitled".
     */
    public static function slug(string $title): string
    {
        $slug = mb_strtolower(trim($title), 'UTF-8');
        $slug = (string) preg_replace('/[^\p{L}\p{N}_\-]+/u', '-', $slug);
        $slug = trim($slug, '-_');
        $slug = mb_substr($slug, 0, 80, 'UTF-8');
        $slug = trim($slug, '-_');

        return $slug === '' ? 'untitled' : $slug;
    }
}
