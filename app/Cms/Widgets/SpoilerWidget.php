<?php

namespace App\Cms\Widgets;

/**
 * {{ spoiler:the answer }}: blurred until tapped, for answers, quiz results
 * or endings. It also shows while focused, so it works without the script.
 */
class SpoilerWidget implements Widget
{
    public function name(): string
    {
        return 'spoiler';
    }

    public function render(string $value): ?string
    {
        $text = trim($value);

        if ($text === '' || mb_strlen($text) > 1000) {
            return null;
        }

        return '<button type="button" class="widget widget-spoiler" aria-expanded="false" title="Show">'
            .'<span class="widget-spoiler-text">'.e($text).'</span>'
            .'</button>';
    }
}
