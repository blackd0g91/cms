<?php

namespace App\Cms\Markdown;

use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Inline\AbstractInline;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;
use League\CommonMark\Renderer\ChildNodeRendererInterface;
use League\CommonMark\Renderer\NodeRendererInterface;
use League\CommonMark\Util\HtmlElement;

/**
 * Keyboard keys: [[Ctrl]] shows a key, and [[Ctrl+Shift+P]] each key of the
 * combination joined by "+". [[+]] is the plus key itself.
 */
class KeyboardKeys implements ExtensionInterface, InlineParserInterface, NodeRendererInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser($this, 100);
        $environment->addRenderer(KeyCombination::class, $this);
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex('\[\[([^\[\]\n]{1,40})\]\]');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        [$combination] = $inlineContext->getSubMatches();

        if (trim($combination) === '') {
            return false;
        }

        // Splitting on "+" keeps a "+" key: "Ctrl++" is Ctrl and +.
        $keys = preg_split('/(?<=.)\+/', trim($combination)) ?: [];
        $keys = array_values(array_filter(array_map('trim', $keys), fn (string $key) => $key !== ''));

        if (str_ends_with(trim($combination), '++')) {
            $keys[] = '+';
        }

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild(new KeyCombination($keys ?: ['+']));

        return true;
    }

    public function render(Node $node, ChildNodeRendererInterface $childRenderer): string
    {
        assert($node instanceof KeyCombination);

        $keys = array_map(fn (string $key) => (string) new HtmlElement('kbd', [], e($key)), $node->keys);

        return count($keys) === 1
            ? $keys[0]
            : (string) new HtmlElement('span', ['class' => 'keys'], implode('<span class="keys-plus">+</span>', $keys));
    }
}

final class KeyCombination extends AbstractInline
{
    /**
     * @param  list<string>  $keys
     */
    public function __construct(public readonly array $keys)
    {
        parent::__construct();
    }
}
