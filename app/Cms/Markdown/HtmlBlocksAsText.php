<?php

namespace App\Cms\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\CommonMark\Node\Block\HtmlBlock;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Node;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Lines of HTML on their own, shown as typed: a paragraph of text with its
 * line breaks, instead of the escaped tags running into the next block.
 */
class HtmlBlocksAsText implements ExtensionInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        // Ahead of the core renderer, which would print the escaped text bare,
        // and of the GitHub tag filter that wraps it (50).
        $environment->addRenderer(HtmlBlock::class, $this, 100);
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): HtmlElement
    {
        assert($node instanceof HtmlBlock);

        return new HtmlElement('p', [], nl2br(e(rtrim($node->getLiteral())), false));
    }
}
