<?php

namespace App\Cms;

use App\Models\Media;
use Illuminate\Contracts\Support\Htmlable;

/**
 * An image field value inside a layout. {{ photo }} renders an <img> tag,
 * while {{# photo }}{{ url }}{{/ photo }} gives access to its parts.
 */
class Image implements Htmlable
{
    public function __construct(
        public readonly string $url,
        public readonly string $alt,
        public readonly ?int $width,
        public readonly ?int $height,
        public readonly string $srcset = '',
        // How wide it is shown, when not the width of the content.
        public readonly ?string $sizes = null,
    ) {}

    public static function fromMedia(Media $media, ?string $sizes = null): self
    {
        return new self($media->url, $media->alt ?? '', $media->width, $media->height, $media->srcset(), $sizes);
    }

    public function toHtml(): string
    {
        $attributes = array_filter([
            'src' => $this->url,
            'srcset' => $this->srcset ?: null,
            'sizes' => $this->srcset ? $this->sizes ?? ResponsiveImages::CONTENT_SIZES : null,
            'alt' => $this->alt,
            'width' => $this->width,
            'height' => $this->height,
            'loading' => 'lazy',
        ], fn ($value) => $value !== null);

        $html = collect($attributes)
            ->map(fn ($value, $name) => $name.'="'.e((string) $value).'"')
            ->implode(' ');

        return "<img {$html}>";
    }
}
