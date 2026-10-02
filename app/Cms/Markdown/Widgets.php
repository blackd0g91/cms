<?php

namespace App\Cms\Markdown;

use App\Cms\Widgets\Widgets as WidgetRegistry;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Inline\AbstractInline;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;

/**
 * Widgets in markdown: {{ name:value }} or just {{ name }}. Unknown names,
 * and values a widget does not accept, are left as written, and code is never
 * touched, so the syntax can still be shown in code.
 *
 * The widget's HTML is kept in a node of its own, not as raw HTML, which
 * markdown escapes (see App\Cms\Markdown).
 */
class Widgets implements ExtensionInterface, InlineParserInterface, NodeRendererInterface
{
    public function __construct(private WidgetRegistry $widgets) {}

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser($this, 100);
        $environment->addRenderer(RenderedWidget::class, $this);
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\{\{\s*([a-z][a-z0-9_-]*)\s*(?::\s*([^{}\n]*?))?\s*\}\}');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $matches = $inlineContext->getSubMatches();
        $html = $this->widgets->get($matches[0])?->render(html_entity_decode($matches[1] ?? '', ENT_QUOTES | ENT_HTML5));

        if ($html === null) {
            return false;
        }

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild(new RenderedWidget($html));

        return true;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        assert($node instanceof RenderedWidget);

        return $node->html;
    }
}

final class RenderedWidget extends AbstractInline
{
    public function __construct(public readonly string $html)
    {
        parent::__construct();
    }
}
