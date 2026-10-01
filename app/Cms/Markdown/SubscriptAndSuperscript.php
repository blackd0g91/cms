<?php

namespace App\Cms\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Inline\AbstractInline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * H~2~O for subscript and x^2^ for superscript. Only without spaces inside,
 * so text like "~10 minutes" or "5 ^ 2" stays as written, and a pair of
 * tildes (~~text~~) is still strikethrough.
 */
class SubscriptAndSuperscript implements ExtensionInterface, InlineParserInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        // Ahead of strikethrough, which would otherwise take single tildes.
        $environment->addInlineParser($this, 100);
        $environment->addRenderer(Subscript::class, $this);
        $environment->addRenderer(Superscript::class, $this);
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('(?:~(?!~)([^\s~]+)~(?!~)|\^([^\s^]+)\^)');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        $matches = $inlineContext->getSubMatches();
        $node = ($matches[0] ?? '') !== '' ? new Subscript : new Superscript;
        $node->appendChild(new Text(($matches[0] ?? '') !== '' ? $matches[0] : (string) ($matches[1] ?? '')));

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild($node);

        return true;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        return new HtmlElement($node instanceof Subscript ? 'sub' : 'sup', [], $childRenderer->renderNodes($node->children()));
    }
}

final class Subscript extends AbstractInline {}

final class Superscript extends AbstractInline {}
