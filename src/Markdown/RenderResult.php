<?php

/**
 * MD.Viewer 3.0 — everything one render produced.
 */

declare(strict_types=1);

namespace MdViewer\Markdown;

final class RenderResult
{
    /**
     * @param array<string, mixed>                                     $frontMatter
     * @param list<array{level:int,text:string,anchor:string,number:?string}> $headings
     */
    public function __construct(
        public readonly string $html,
        public readonly array $frontMatter = [],
        public readonly array $headings = [],
        public readonly string $tocHtml = '',
        public readonly ?string $title = null,
        public readonly ?string $eyebrow = null,
        public readonly string $description = '',
        public readonly int $words = 0,
        public readonly int $readingMinutes = 1,
        public readonly bool $hasMermaid = false,
        public readonly bool $hasMath = false,
        public readonly bool $hasImages = false,
        public readonly int $glossaryTerms = 0,
    ) {
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'html' => $this->html,
            'frontMatter' => $this->frontMatter,
            'headings' => $this->headings,
            'tocHtml' => $this->tocHtml,
            'title' => $this->title,
            'eyebrow' => $this->eyebrow,
            'description' => $this->description,
            'words' => $this->words,
            'readingMinutes' => $this->readingMinutes,
            'hasMermaid' => $this->hasMermaid,
            'hasMath' => $this->hasMath,
            'hasImages' => $this->hasImages,
            'glossaryTerms' => $this->glossaryTerms,
        ];
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            html: (string) ($data['html'] ?? ''),
            frontMatter: (array) ($data['frontMatter'] ?? []),
            headings: array_values((array) ($data['headings'] ?? [])),
            tocHtml: (string) ($data['tocHtml'] ?? ''),
            title: $data['title'] !== null ? (string) $data['title'] : null,
            eyebrow: $data['eyebrow'] !== null ? (string) $data['eyebrow'] : null,
            description: (string) ($data['description'] ?? ''),
            words: (int) ($data['words'] ?? 0),
            readingMinutes: (int) ($data['readingMinutes'] ?? 1),
            hasMermaid: (bool) ($data['hasMermaid'] ?? false),
            hasMath: (bool) ($data['hasMath'] ?? false),
            hasImages: (bool) ($data['hasImages'] ?? false),
            glossaryTerms: (int) ($data['glossaryTerms'] ?? 0),
        );
    }
}
