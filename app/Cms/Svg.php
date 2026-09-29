<?php

namespace App\Cms;

use enshrined\svgSanitize\Sanitizer;

/**
 * Cleans uploaded SVGs so they are safe to serve from the site: scripts,
 * event handlers, external references and other active content are removed.
 */
class Svg
{
    /**
     * The cleaned SVG, or null if the file is not a readable SVG.
     */
    public function sanitize(string $svg): ?string
    {
        $sanitizer = new Sanitizer;
        $sanitizer->removeRemoteReferences(true);

        $clean = $sanitizer->sanitize($svg);

        return is_string($clean) && str_contains($clean, '<svg') ? $clean : null;
    }

    /**
     * The intrinsic size from the width/height attributes or the viewBox.
     *
     * @return array{0: int|null, 1: int|null}
     */
    public function dimensions(string $svg): array
    {
        $root = @simplexml_load_string($svg);

        if ($root === false) {
            return [null, null];
        }

        $width = $this->length((string) $root['width']);
        $height = $this->length((string) $root['height']);

        if ($width === null || $height === null) {
            $viewBox = preg_split('/[\s,]+/', trim((string) $root['viewBox'])) ?: [];

            if (count($viewBox) === 4 && is_numeric($viewBox[2]) && is_numeric($viewBox[3])) {
                [$width, $height] = [(int) round((float) $viewBox[2]), (int) round((float) $viewBox[3])];
            }
        }

        return [$width ?: null, $height ?: null];
    }

    /**
     * A plain or pixel length as an integer. Percentages and other units
     * say nothing about the image's own size.
     */
    private function length(string $value): ?int
    {
        return preg_match('/^\s*([\d.]+)\s*(px)?\s*$/', $value, $match)
            ? (int) round((float) $match[1])
            : null;
    }
}
