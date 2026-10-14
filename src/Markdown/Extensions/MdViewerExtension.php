<?php

/**
 * MD.Viewer 3.0 — the aggregate custom extension.
 *
 * Registers every MD.Viewer parser, renderer and listener on top of
 * CommonMark + GFM. Feature-dependent *parsers* are registered (or not) at
 * build time; *renderers* always register and consult the render context, so
 * one converter per feature fingerprint stays cacheable.
 */

declare(strict_types=1);

namespace MdViewer\Markdown\Extensions;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Event\DocumentParsedEvent;
use League\CommonMark\Extension\CommonMark\Node\Block\BlockQuote;
use League\CommonMark\Extension\CommonMark\Node\Block\FencedCode;
use League\CommonMark\Extension\CommonMark\Node\Block\Heading;
use League\CommonMark\Extension\CommonMark\Node\Block\IndentedCode;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Node\Inline\Newline;
use MdViewer\Markdown\ContextHolder;
use MdViewer\Markdown\Extensions\Node\MathInline;
use MdViewer\Markdown\Extensions\Node\SubInline;
use MdViewer\Markdown\Extensions\Node\SupInline;

final class MdViewerExtension implements ExtensionInterface
{
    /**
     * @param array<string, bool|string> $features resolved feature snapshot
     */
    public function __construct(
        private readonly ContextHolder $holder,
        private readonly array $features = [],
    ) {
    }

    public function register(EnvironmentBuilderInterface $environment): void
    {
        // ── Inline parsers (feature-gated at registration) ─────────────
        if ($this->on('FEATURE_MATH')) {
            $environment->addInlineParser(new MathInlineParser($this->holder), 60);
        }
        if ($this->on('FEATURE_SUBSUP')) {
            $environment->addInlineParser(new SupInlineParser(), 55);
            $environment->addInlineParser(new SubInlineParser(), 55);
        }
        if ($this->on('FEATURE_EMOJI')) {
            $environment->addInlineParser(new EmojiInlineParser(), 50);
        }

        // ── Renderers (priority 10 beats the core renderers) ───────────
        $environment->addRenderer(Heading::class, new HeadingRenderer(), 10);
        $environment->addRenderer(\League\CommonMark\Node\Block\Paragraph::class, new ParagraphRenderer(), 10);
        $environment->addRenderer(BlockQuote::class, new AlertBlockQuoteRenderer($this->holder), 10);
        $environment->addRenderer(FencedCode::class, new CodeBlockRenderer($this->holder), 10);
        $environment->addRenderer(IndentedCode::class, new CodeBlockRenderer($this->holder), 10);
        $environment->addRenderer(Code::class, new CodeSpanRenderer($this->holder), 10);
        $environment->addRenderer(Link::class, new LinkRenderer($this->holder), 10);
        $environment->addRenderer(Image::class, new ImageRenderer($this->holder), 10);
        $environment->addRenderer(Newline::class, new NewlineRenderer($this->holder), 10);
        $environment->addRenderer(MathInline::class, new MathInlineRenderer(), 10);
        $environment->addRenderer(SupInline::class, new SupInlineRenderer(), 10);
        $environment->addRenderer(SubInline::class, new SubInlineRenderer(), 10);

        // ── Listeners ───────────────────────────────────────────────────
        $environment->addEventListener(DocumentParsedEvent::class, new HeadingProcessor($this->holder), 100);
    }

    private function on(string $key): bool
    {
        return (bool) ($this->features[$key] ?? true);
    }
}
