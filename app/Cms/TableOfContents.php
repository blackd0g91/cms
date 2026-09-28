<?php

namespace App\Cms;

use Illuminate\Support\Str;

/**
 * Builds a table of contents from a rendered post's h2 and h3 headings,
 * giving each heading an id so it can be linked to.
 */
class TableOfContents
{
    /**
     * @return array{html: string, headings: list<array{id: string, text: string, level: int}>}
     */
    public function build(string $html): array
    {
        $headings = [];
        $used = [];

        $html = (string) preg_replace_callback(
            '/<h([23])(\s[^>]*)?>(.*?)<\/h\1>/si',
            function (array $match) use (&$headings, &$used) {
                [$tag, $level, $attributes, $inner] = [$match[0], (int) $match[1], $match[2], $match[3]];
                $text = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5));

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
                $headings[] = ['id' => $id, 'text' => $text, 'level' => $level];

                return "<h{$level}{$attributes}>{$inner}</h{$level}>";
            },
            $html,
        );

        return ['html' => $html, 'headings' => $headings];
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
