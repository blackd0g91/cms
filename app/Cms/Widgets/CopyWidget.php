<?php

namespace App\Cms\Widgets;

/**
 * {{ copy:npm run dev }}: the text with a button that copies it. With a
 * label, {{ copy:hunter2 | Wi-Fi password }}, the label shows instead, so
 * the text itself is not printed on the page.
 */
class CopyWidget implements ReadsAsText, Widget
{
    public function name(): string
    {
        return 'copy';
    }

    public function text(string $value): ?string
    {
        [$text, $label] = array_pad(array_map('trim', explode('|', $value, 2)), 2, '');

        if ($this->render($value) === null) {
            return null;
        }

        return $label !== '' ? $label : $text;
    }

    public function render(string $value): ?string
    {
        [$text, $label] = array_pad(array_map('trim', explode('|', $value, 2)), 2, '');

        if ($text === '' || mb_strlen($text) > 2000) {
            return null;
        }

        return '<span class="widget widget-copy" data-copy="'.e($text).'">'
            .($label !== ''
                ? '<span class="widget-copy-label">'.e($label).'</span>'
                : '<code class="widget-copy-text">'.e($text).'</code>')
            .'<button type="button" class="widget-copy-button" aria-label="Copy '.e($label !== '' ? $label : $text).'">'
            .'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 7m0 2.667a2.667 2.667 0 0 1 2.667 -2.667h8.666a2.667 2.667 0 0 1 2.667 2.667v8.666a2.667 2.667 0 0 1 -2.667 2.667h-8.666a2.667 2.667 0 0 1 -2.667 -2.667z"/><path d="M4.012 16.737a2.005 2.005 0 0 1 -1.012 -1.737v-10c0 -1.1 .9 -2 2 -2h10c.75 0 1.158 .385 1.5 1"/></svg>'
            .'<span class="widget-copy-done">Copied</span>'
            .'</button>'
            .'</span>';
    }
}
