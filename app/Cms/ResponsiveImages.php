<?php

namespace App\Cms;

use App\Models\Media;

/**
 * Gives <img> tags that point at uploaded images (for example ones inserted
 * into markdown) their resized versions, size and lazy loading.
 */
class ResponsiveImages
{
    /**
     * Content is at most about 768px wide, so this is enough for sharp images.
     */
    public const string CONTENT_SIZES = '(min-width: 768px) 768px, 100vw';

    public function apply(string $html): string
    {
        if (! preg_match_all('/<img\s[^>]*src="[^"]*\/(media\/[^"\/]+)"/i', $html, $matches)) {
            return $html;
        }

        $media = Media::query()->whereIn('path', array_unique($matches[1]))->get()->keyBy('path');

        return (string) preg_replace_callback('/<img\s[^>]*>/i', function (array $match) use ($media) {
            $tag = $match[0];

            if (! preg_match('/src="[^"]*\/(media\/[^"\/]+)"/i', $tag, $src) || ! $item = $media->get($src[1])) {
                return $tag;
            }

            $extra = array_filter([
                'srcset' => $item->srcset() ?: null,
                'sizes' => $item->srcset() ? self::CONTENT_SIZES : null,
                'width' => $item->width,
                'height' => $item->height,
                'loading' => 'lazy',
            ], fn ($value) => $value !== null);

            // Drop the closing ">" (or " />"), add what is missing, close again.
            $tag = (string) preg_replace('/\s*\/?>$/', '', $tag);

            foreach ($extra as $name => $value) {
                if (! preg_match('/\s'.$name.'=/i', $tag)) {
                    $tag .= ' '.$name.'="'.e((string) $value).'"';
                }
            }

            return $tag.'>';
        }, $html);
    }
}
