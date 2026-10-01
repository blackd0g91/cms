<?php

namespace App\Cms\Widgets;

/**
 * Something interactive or generated, placed in markdown with
 * {{ name:value }} (see App\Cms\Markdown\Widgets).
 */
interface Widget
{
    /**
     * What is written before the colon, like "qr".
     */
    public function name(): string;

    /**
     * The HTML for the widget, or null when the value makes no sense for it,
     * which leaves {{ … }} as written.
     */
    public function render(string $value): ?string;
}
