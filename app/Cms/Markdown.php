<?php

namespace App\Cms;

use App\Cms\Markdown\EmojiShortcodes;
use App\Cms\Markdown\SubscriptAndSuperscript;
use Illuminate\Container\Attributes\Singleton;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Highlight\HighlightExtension;
use League\CommonMark\MarkdownConverter;
use Phiki\Adapters\CommonMark\PhikiExtension;
use Phiki\Theme\Theme;

/**
 * GitHub flavored markdown (tables, task lists, strikethrough, autolinks)
 * with syntax highlighted code blocks, plus the extended syntax: footnotes,
 * definition lists, ==highlight==, H~2~O and x^2^, {#heading-ids} and
 * :shortcode: emoji.
 *
 * Code is highlighted with a light theme, with dark theme colors exposed as
 * CSS variables (see resources/css/code.css).
 */
#[Singleton]
class Markdown
{
    private MarkdownConverter $converter;

    public function __construct()
    {
        $environment = new Environment([
            'allow_unsafe_links' => false,
            // {#id .class} after headings, links and other blocks. Only plain
            // attributes: nothing that could run code or restyle the page.
            'attributes' => [
                'allow' => ['id', 'class', 'title', 'lang', 'target', 'rel'],
            ],
            // The text form of the arrow (U+FE0E), not the emoji one.
            'footnote' => [
                'backref_symbol' => "\u{21A9}\u{FE0E}",
            ],
        ]);

        $environment->addExtension(new CommonMarkCoreExtension);
        $environment->addExtension(new GithubFlavoredMarkdownExtension);
        $environment->addExtension(new EmojiShortcodes);
        $environment->addExtension(new FootnoteExtension);
        $environment->addExtension(new DescriptionListExtension);
        $environment->addExtension(new HighlightExtension);
        $environment->addExtension(new AttributesExtension);
        $environment->addExtension(new SubscriptAndSuperscript);
        $environment->addExtension(new PhikiExtension([
            'light' => Theme::GithubLight,
            'dark' => Theme::GithubDark,
        ]));

        $this->converter = new MarkdownConverter($environment);
    }

    public function render(string $markdown): string
    {
        return $this->converter->convert($markdown)->getContent();
    }
}
