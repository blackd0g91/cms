<?php

namespace App\Cms;

use Illuminate\Support\Str;

/**
 * Builds a table of contents from a rendered post's headings. Every heading
 * gets an id and becomes a link to itself, so any section can be linked to
 * (.heading-link, in resources/css/site.css and resources/js/site.ts).
 *
 * The table lists two levels, starting from the post's sections: sections
 * written with # and ## in markdown work as well as ones written with ## and
 * ###, and a single title at the top of a markdown file (# Title, then ##
 * sections) is left out.
 */
class TableOfContents
{
    /**
     * @return array{html: string, headings: list<array{id: string, text: string, level: int, depth: int}>}
     */
    public function build(string $html): array
    {
        $top = $this->topLevel($html);

        if ($top === null) {
            return ['html' => $html, 'headings' => []];
        }

        $headings = [];
        $used = [];

        $html = (string) preg_replace_callback(
            '/<h([1-6])(\s[^>]*)?>(.*?)<\/h\1>/si',
            function (array $match) use ($top, &$headings, &$used) {
                [$tag, $level, $attributes, $inner] = [$match[0], (int) $match[1], $match[2], $match[3]];
                $text = self::text($inner);

                if ($text === '') {
                    return $tag;
                }

                if (preg_match('/\sid=(["\'])(.*?)\1/i', $attributes, $existing)) {
                    $id = $existing[2];
                } else {
                    $id = $this->uniqueId(Str::slug($text) ?: 'section', $used);
                    $attributes .= ' id="'.e($id).'"';
                }

                $used[$id] = true;

                if ($level === $top || $level === $top + 1) {
                    // 1 for the top level, 2 for the one below it.
                    $headings[] = ['id' => $id, 'text' => $text, 'level' => $level, 'depth' => $level - $top + 1];
                }

                // Links can't be nested, so one holding a link stays as it is.
                if (! preg_match('/<a[\s>]/i', $inner)) {
                    $inner = '<a class="heading-link" href="#'.e($id).'">'.$inner.'</a>';
                }

                return "<h{$level}{$attributes}>{$inner}</h{$level}>";
            },
            $html,
        );

        return ['html' => $html, 'headings' => $headings];
    }

    /**
     * The level of the post's sections: the highest one used, except for a
     * single h1 above other headings, which is a title (like # Title at the
     * top of a markdown file). Null without headings.
     */
    private function topLevel(string $html): ?int
    {
        preg_match_all('/<h([1-6])(?:\s[^>]*)?>(.*?)<\/h\1>/si', $html, $matches, PREG_SET_ORDER);

        $counts = array_count_values(array_map(
            fn (array $match) => (int) $match[1],
            array_filter($matches, fn (array $match) => self::text($match[2]) !== ''),
        ));

        if (($counts[1] ?? 0) === 1 && count($counts) > 1) {
            unset($counts[1]);
        }

        return $counts === [] ? null : min(array_keys($counts));
    }

    private static function text(string $inner): string
    {
        return trim(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5));
    }

    /**
     * @param  array<string, bool>  $used
     */
    private function uniqueId(string $id, array $used): string
    {
        $candidate = $id;

        for ($i = 2; isset($used[$candidate]); $i++) {
            $candidate = "{$id}-{$i}";
        }

        return $candidate;
    }
}
