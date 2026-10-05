<?php

namespace App\Cms\Widgets;

/**
 * A widget with words of its own, for where a post is read as plain text,
 * like the summary on its card: "200 g", "180 °C". Widgets without it, like
 * a spoiler or a QR code, are left out there.
 */
interface ReadsAsText
{
    /**
     * The widget as text, or null when the value makes no sense for it.
     */
    public function text(string $value): ?string;
}
