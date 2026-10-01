<?php

namespace App\Cms\Markdown;

use App\Cms\Widgets\Widgets as WidgetRegistry;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * Widgets in markdown: {{ name:value }} or just {{ name }}. Unknown names,
 * and values a widget does not accept, are left as written, and code is never
 * touched, so the syntax can still be shown in code.
 */
class Widgets implements ExtensionInterface, InlineParserInterface
{
    public function __construct(private WidgetRegistry $widgets) {}

    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser($this, 100);
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
        $inlineContext->getContainer()->appendChild(new HtmlInline($html));

        return true;
    }
}
