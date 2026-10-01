<?php

namespace App\Cms\Markdown;

use App\Cms\Emoji;
use League\CommonMark\Environment\EnvironmentBuilderInterface;
use League\CommonMark\Extension\ExtensionInterface;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Parser\Inline\InlineParserInterface;
use League\CommonMark\Parser\Inline\InlineParserMatch;
use League\CommonMark\Parser\InlineParserContext;

/**
 * Turns :shortcodes: into emoji, like :pizza: into 🍕 (GitHub's names).
 * Unknown names, like the minutes in 10:30:45, are left as typed, and code
 * is never touched, since inline parsers do not run inside it.
 */
class EmojiShortcodes implements ExtensionInterface, InlineParserInterface
{
    public function register(EnvironmentBuilderInterface $environment): void
    {
        $environment->addInlineParser($this);
    }

    public function getMatchDefinition(): InlineParserMatch
    {
        return InlineParserMatch::regex(':([a-z0-9_+\-]+):');
    }

    public function parse(InlineParserContext $inlineContext): bool
    {
        [$shortcode] = $inlineContext->getSubMatches();
        $emoji = Emoji::fromShortcode($shortcode);

        if ($emoji === null) {
            return false;
        }

        $inlineContext->getCursor()->advanceBy($inlineContext->getFullMatchLength());
        $inlineContext->getContainer()->appendChild(new Text($emoji));

        return true;
    }
}
