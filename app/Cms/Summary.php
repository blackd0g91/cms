<?php

namespace App\Cms;

use App\Cms\Markdown\EmojiShortcodes;
use App\Cms\Markdown\KeyboardKeys;
use App\Cms\Markdown\KeyCombination;
use App\Cms\Markdown\SubscriptAndSuperscript;
use App\Cms\Widgets\ReadsAsText;
use App\Cms\Widgets\Widgets;
use App\Enums\FieldType;
use App\Models\Post;
use Illuminate\Container\Attributes\Singleton;
use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\Attributes\AttributesExtension;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\CommonMark\Node\Block\ListBlock;
use League\CommonMark\Extension\CommonMark\Node\Inline\Code;
use League\CommonMark\Extension\CommonMark\Node\Inline\HtmlInline;
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\DescriptionList\DescriptionListExtension;
use League\CommonMark\Extension\Footnote\FootnoteExtension;
use League\CommonMark\Extension\Footnote\Node\FootnoteRef;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\Extension\Highlight\HighlightExtension;
use League\CommonMark\Node\Block\Paragraph;
use League\CommonMark\Node\Inline\Newline;
use League\CommonMark\Node\Inline\Text;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;

/**
 * A post's opening words as plain text, for its card, its description in
 * link previews and its feed entry: its first paragraphs, as they read on
 * the page. Headings, images, code blocks, quotes, tables and footnotes are
 * left out, and so are widgets without words of their own (see ReadsAsText).
 */
#[Singleton]
class Summary
{
    private MarkdownParser $parser;

    public function __construct(private Widgets $widgets)
    {
        // The syntax that changes what text is a paragraph or how it reads
        // (see App\Cms\Markdown). Widgets are read below, without drawing them.
        $environment = new Environment(['html_input' => 'escape']);

        foreach ([
            new CommonMarkCoreExtension,
            new GithubFlavoredMarkdownExtension,
            new FootnoteExtension,
            new DescriptionListExtension,
            new HighlightExtension,
            new AttributesExtension,
            new SubscriptAndSuperscript,
            new KeyboardKeys,
            new EmojiShortcodes,
        ] as $extension) {
            $environment->addExtension($extension);
        }

        $this->parser = new MarkdownParser($environment);
    }

    public function of(Post $post, int $length = 180): string
    {
        $paragraphs = [];
        // Only used for posts with nothing else, like a list of things used.
        $listItems = [];

        foreach ($post->template->fieldTypes() as $handle => $type) {
            $value = $post->data[$handle] ?? null;

            if ($type === FieldType::Text || $type === FieldType::Textarea) {
                $paragraphs[] = is_string($value) ? $value : '';
            } elseif ($type === FieldType::Markdown && is_string($value)) {
                $this->read($value, $paragraphs, $listItems);
            } elseif ($type === FieldType::List) {
                array_push($listItems, ...array_filter((array) $value, is_string(...)));
            }
        }

        $summary = implode(' ', array_map(self::sentence(...), array_filter(array_map(self::tidy(...), $paragraphs))));

        if ($summary === '') {
            $summary = self::tidy(implode(', ', array_filter(array_map(self::tidy(...), $listItems))));
        }

        return Str::limit($summary, $length, '…', preserveWords: true);
    }

    /**
     * Add the markdown's paragraphs, and separately the items of its lists.
     *
     * @param  list<string>  $paragraphs
     * @param  list<string>  $listItems
     */
    private function read(string $markdown, array &$paragraphs, array &$listItems): void
    {
        foreach ($this->parser->parse($markdown)->children() as $block) {
            if ($block instanceof Paragraph) {
                $paragraphs[] = $this->text($block);
            } elseif ($block instanceof ListBlock) {
                foreach ($block->iterator() as $node) {
                    if ($node instanceof Paragraph) {
                        $listItems[] = $this->text($node);
                    }
                }
            }
        }
    }

    /**
     * A paragraph's words, with widgets read as text. An image within a
     * sentence reads as its alt text; a paragraph of only images has none.
     */
    private function text(Node $node): string
    {
        if (self::tidy($this->words($node, images: false)) === '') {
            return '';
        }

        return (string) preg_replace_callback(
            '/\{\{\s*([a-z][a-z0-9_-]*)\s*(?::\s*([^{}\n]*?))?\s*\}\}/i',
            function (array $match) {
                $widget = $this->widgets->get($match[1]);

                return match (true) {
                    // Unknown names are shown as written on the page too.
                    $widget === null => $match[0],
                    $widget instanceof ReadsAsText => $widget->text($match[2] ?? '') ?? '',
                    default => '',
                };
            },
            $this->words($node, images: true),
        );
    }

    private function words(Node $node, bool $images): string
    {
        $words = '';

        foreach ($node->children() as $child) {
            $words .= match (true) {
                $child instanceof Text, $child instanceof Code => $child->getLiteral(),
                $child instanceof Newline => ' ',
                $child instanceof KeyCombination => implode('+', $child->keys),
                $child instanceof Image => $images ? $this->words($child, $images) : '',
                $child instanceof FootnoteRef, $child instanceof HtmlInline => '',
                // Emphasis, links, highlights and the like: their own words.
                default => $this->words($child, $images),
            };
        }

        return $words;
    }

    /**
     * A paragraph ends like a sentence, so the next one does not run into
     * it: "Makes 12 cookies. Mix well."
     */
    private static function sentence(string $paragraph): string
    {
        return preg_match('/[.!?…:;,]$/u', $paragraph) ? $paragraph : "{$paragraph}.";
    }

    /**
     * Single spaces, and none before punctuation, which a left out widget
     * can leave behind: "Bake at ." becomes "Bake at.".
     */
    private static function tidy(string $text): string
    {
        return trim((string) preg_replace(['/\s+/u', '/\s+([.,;:!?])/u'], [' ', '$1'], $text));
    }
}
