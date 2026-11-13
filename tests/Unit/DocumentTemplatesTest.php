<?php

declare(strict_types=1);

namespace MdViewer\Tests\Unit;

use MdViewer\Files\DocumentTemplates;
use MdViewer\Tests\TestCase;

final class DocumentTemplatesTest extends TestCase
{
    public function testEveryTemplateRenders(): void
    {
        $all = DocumentTemplates::all();
        self::assertGreaterThanOrEqual(5, count($all));

        foreach (array_keys($all) as $id) {
            $content = DocumentTemplates::render($id, 'Restore drill');
            self::assertNotNull($content, $id);
            self::assertStringNotContainsString('{{title}}', $content, $id);
            self::assertStringNotContainsString('{{date}}', $content, $id);
            self::assertStringNotContainsString('{{slug}}', $content, $id);
            self::assertStringContainsString('Restore drill', $content, $id);
        }
    }

    public function testUnknownTemplateReturnsNull(): void
    {
        self::assertNull(DocumentTemplates::get('nope'));
        self::assertNull(DocumentTemplates::render('nope', 'Title'));
    }

    public function testSlugIsFileSafeAndUnicodeAware(): void
    {
        self::assertSame('restore-drill', DocumentTemplates::slug('Restore drill'));
        self::assertSame('привет-мир-2026', DocumentTemplates::slug('Привет, мир! 2026'));
        self::assertSame('a-b', DocumentTemplates::slug('  a /// b  '));
        self::assertSame('untitled', DocumentTemplates::slug('!!!'));
        self::assertSame(80, mb_strlen(DocumentTemplates::slug(str_repeat('x', 200))));
    }

    public function testAdrTemplateCarriesFrontMatterAndSections(): void
    {
        $content = (string) DocumentTemplates::render('adr', 'Adopt SQLite');

        self::assertStringStartsWith('---', $content);
        self::assertStringContainsString('tags: [adr]', $content);
        self::assertStringContainsString('## Status', $content);
        self::assertStringContainsString('## Consequences', $content);
    }
}
