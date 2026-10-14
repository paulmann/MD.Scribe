<?php

declare(strict_types=1);

namespace MdViewer\Tests\Unit;

use MdViewer\Core\FeatureFlags;
use MdViewer\Markdown\ExcerptService;
use MdViewer\Markdown\GlossaryService;
use MdViewer\Markdown\MermaidNormalizer;
use MdViewer\Markdown\SourcePreprocessor;
use MdViewer\Markdown\TableAnnotator;
use MdViewer\Markdown\UniversalPattern;
use MdViewer\Tests\TestCase;

final class MarkdownServicesTest extends TestCase
{
    // ── FeatureFlags ───────────────────────────────────────────────────────

    public function testFlagPrecedenceIniOverCookieOverDefault(): void
    {
        file_put_contents($this->tmpRoot . '/.md.ini', "FEATURE_EMOJI = false\n");
        $config = $this->config();
        $flags = new FeatureFlags($config, ['mdv_feat_FEATURE_EMOJI' => '1', 'mdv_feat_AUTO_TOC' => '0']);

        self::assertFalse($flags->enabled('FEATURE_EMOJI'));   // ini hard-lock wins
        self::assertFalse($flags->enabled('AUTO_TOC'));        // cookie overrides default
        self::assertTrue($flags->enabled('FEATURE_MATH'));     // schema default
    }

    public function testNonFeatureKeysIgnoreCookies(): void
    {
        $config = $this->config();
        $flags = new FeatureFlags($config, ['mdv_feat_ALLOW_UPDATE' => '1']);
        self::assertFalse($flags->get('ALLOW_UPDATE')); // security keys are never cookie-toggled
    }

    // ── ExcerptService ─────────────────────────────────────────────────────

    public function testFrontMatterParsing(): void
    {
        [$fm, $body] = ExcerptService::extractFrontMatter("---\ntitle: Test\ndraft: true\ntags: [a, b]\nlist:\n  - one\n  - two\n---\n\nBody here\n");
        self::assertSame('Test', $fm['title']);
        self::assertTrue($fm['draft']);
        self::assertSame(['a', 'b'], $fm['tags']);
        self::assertSame(['one', 'two'], $fm['list']);
        self::assertSame("\nBody here\n", $body);
    }

    public function testNoFrontMatter(): void
    {
        [$fm, $body] = ExcerptService::extractFrontMatter("# Title\n");
        self::assertSame([], $fm);
        self::assertSame("# Title\n", $body);
    }

    public function testHeroTitleSplit(): void
    {
        self::assertSame(['title' => 'Sub', 'eyebrow' => 'Topic'], ExcerptService::heroTitle('Topic: Sub', true));
        self::assertSame(['title' => 'Topic: Sub', 'eyebrow' => null], ExcerptService::heroTitle('Topic: Sub', false));
        self::assertSame(['title' => 'No colon', 'eyebrow' => null], ExcerptService::heroTitle('No colon', true));
    }

    public function testReadingTimeCountsCjk(): void
    {
        $t = ExcerptService::readingTime(str_repeat('中文测试', 60));
        self::assertGreaterThan(100, $t['words']);
        self::assertGreaterThanOrEqual(1, $t['minutes']);
    }

    public function testManualNumberingDetection(): void
    {
        self::assertTrue(ExcerptService::hasManualNumbering("# 1. Intro\n"));
        self::assertTrue(ExcerptService::hasManualNumbering("## 2.3 Deep\n"));
        self::assertFalse(ExcerptService::hasManualNumbering("# Plain\n"));
        self::assertFalse(ExcerptService::hasManualNumbering("```\n# 1. In code\n```\n"));
    }

    // ── GlossaryService ────────────────────────────────────────────────────

    public function testGlossaryParseWithAliases(): void
    {
        $glossary = GlossaryService::parse("| Term | Definition |\n|---|---|\n| NF (Neurofeedback) | A method |\n| API | Contract |\n");
        self::assertArrayHasKey('nf (neurofeedback)', $glossary);
        self::assertArrayHasKey('nf', $glossary);
        self::assertArrayHasKey('neurofeedback', $glossary);
        self::assertSame('tbl-1', $glossary['api']['slug']);
        self::assertSame(1, $glossary['api']['row']);
        self::assertSame(2, $glossary['api']['cols']);
    }

    public function testGlossarySkipsUnnamedFirstColumn(): void
    {
        $glossary = GlossaryService::parse("| | Value |\n|---|---|\n| a | b |\n");
        self::assertSame([], $glossary);
    }

    public function testGlossaryApplyProtectsCodeAndLinks(): void
    {
        $glossary = GlossaryService::parse("| Term | Definition |\n|---|---|\n| API | Contract |\n");
        $html = '<p>The API and <code>API</code> and <a href="#">API</a>.</p>';
        $out = GlossaryService::apply($html, $glossary);
        self::assertSame(1, substr_count($out, 'glossary-term'));
    }

    // ── TableAnnotator ─────────────────────────────────────────────────────

    public function testTableAnnotationContract(): void
    {
        $html = '<div class="md-table-wrap"><table><thead><tr><th>Term</th><th>Def</th></tr></thead>'
            . '<tbody><tr><td>API</td><td>Contract</td></tr><tr><td>NF</td><td>Method</td></tr></tbody></table></div>';
        $out = TableAnnotator::annotate($html);
        self::assertStringContainsString('id="tbl-1"', $out);
        self::assertStringContainsString('id="gd-tbl-1-0-1" data-gc="1"', $out);
        self::assertStringContainsString('id="gd-tbl-1-1-2"', $out);
    }

    // ── MermaidNormalizer ──────────────────────────────────────────────────

    public function testMermaidRepairsLiteralNewlineAndBr(): void
    {
        $result = MermaidNormalizer::repair("graph TD\n    A[Line1\\nLine2]\n    B[Text<br>More]\n");
        self::assertStringContainsString('<br/>', $result['source']);
        self::assertStringNotContainsString('\\n', $result['source']);
        $rules = array_column($result['fixes'], 'rule');
        self::assertContains('literal-newline', $rules);
        self::assertContains('unclosed-br', $rules);
    }

    public function testMermaidQuotesParenthesesLabels(): void
    {
        $result = MermaidNormalizer::repair("graph TD\n    B(End (x))\n");
        self::assertStringContainsString('B("End (x)")', $result['source']);
        self::assertContains('unquoted-parentheses', array_column($result['fixes'], 'rule'));
    }

    public function testMermaidSequenceReturn(): void
    {
        $result = MermaidNormalizer::repair("sequenceDiagram\n    A->>B: call\n    return response\n");
        self::assertStringContainsString('B-->>A: response', $result['source']);
        self::assertContains('plantuml-return', array_column($result['fixes'], 'rule'));
    }

    public function testMermaidOrphanReturnCommented(): void
    {
        $result = MermaidNormalizer::repair("sequenceDiagram\n    return lonely\n");
        self::assertStringContainsString('%% return lonely', $result['source']);
    }

    public function testMermaidClassDiagramNotes(): void
    {
        $result = MermaidNormalizer::repair("classDiagram\n    note right of Animal\n      Eats food\n    end note\n");
        self::assertStringContainsString('note for Animal "Eats food"', $result['source']);
    }

    public function testMermaidSequenceDiagramSkipsNodeQuoting(): void
    {
        $result = MermaidNormalizer::repair("sequenceDiagram\n    A->>B: hi (there)\n");
        self::assertSame('hi (there)', trim(explode(': ', $result['source'])[1] ?? ''));
    }

    // ── UniversalPattern ───────────────────────────────────────────────────

    public function testPatternChainParsed(): void
    {
        $elements = UniversalPattern::parse('(A) --> [B: uses] --> (C)');
        self::assertNotNull($elements);
        self::assertSame('node', $elements[0]['type']);
        self::assertSame('rel', $elements[1]['type']);
        self::assertSame('right', $elements[1]['direction']);
    }

    public function testPlainCodeNotAChain(): void
    {
        self::assertNull(UniversalPattern::parse('const x = 1;'));
        self::assertNull(UniversalPattern::parse(''));
    }

    public function testLoneAngleBracketTerminatesV216(): void
    {
        // v2.16: this exact input once spun to the 30 s PHP limit.
        $start = hrtime(true);
        $result = UniversalPattern::parse('--basetemp=<path>');
        $elapsed = (hrtime(true) - $start) / 1e9;
        self::assertLessThan(1.0, $elapsed);
        self::assertTrue($result === null || is_array($result));
    }

    // ── SourcePreprocessor ─────────────────────────────────────────────────

    public function testMathBlocksExtracted(): void
    {
        $result = SourcePreprocessor::process("before\n\n\$\$\nx^2\n\$\$\n\nafter\n");
        self::assertCount(1, $result['mathBlocks']);
        self::assertSame('x^2', $result['mathBlocks'][0]);
        self::assertStringContainsString('@@MDVMATH0@@', $result['source']);
    }

    public function testMathInsideFenceUntouched(): void
    {
        $result = SourcePreprocessor::process("```\n\$\$\nx^2\n\$\$\n```\n");
        self::assertCount(0, $result['mathBlocks']);
    }

    public function testTocToken(): void
    {
        $result = SourcePreprocessor::process("text\n\n[TOC]\n\nmore\n");
        self::assertStringContainsString('@@MDVTOC@@', $result['source']);
    }

    public function testMathRestore(): void
    {
        $html = SourcePreprocessor::restore('<p>@@MDVMATH0@@</p>', ['x^2']);
        self::assertStringContainsString('mdx-math-block', $html);
        self::assertStringContainsString('x^2', $html);
        self::assertStringNotContainsString('@@MDVMATH0@@', $html);
    }
}
