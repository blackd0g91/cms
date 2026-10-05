<?php

namespace App\Cms;

use App\Models\Media;
use ArrayIterator;
use Illuminate\Contracts\Support\Htmlable;
use IteratorAggregate;
use Traversable;

/**
 * A gallery field value inside a layout. {{ photos }} renders the images in
 * a row that scrolls sideways (.gallery in resources/css/site.css), each
 * opening larger when clicked, while {{# photos }}{{ url }}{{/ photos }}
 * goes through them one at a time, with the same parts as an image field.
 *
 * @implements IteratorAggregate<int, Image>
 */
class Gallery implements Htmlable, IteratorAggregate
{
    /**
     * How tall the images are shown, in pixels, so the browser can pick a
     * resized copy that is wide enough.
     */
    private const int HEIGHT = 256;

    /**
     * @param  list<Image>  $images
     */
    public function __construct(public readonly array $images) {}

    /**
     * The images that still exist, in order, or null when none do.
     *
     * @param  array<int, mixed>  $ids
     */
    public static function fromIds(array $ids): ?self
    {
        $ids = array_values(array_filter($ids, is_int(...)));
        $media = Media::query()->whereKey($ids)->get()->keyBy('id');
        $images = [];

        foreach ($ids as $id) {
            if ($item = $media->get($id)) {
                $images[] = Image::fromMedia($item, self::sizes($item));
            }
        }

        return $images === [] ? null : new self($images);
    }

    /**
     * @return Traversable<int, Image>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->images);
    }

    public function toHtml(): string
    {
        return '<div class="gallery">'.implode('', array_map(fn (Image $image) => $image->toHtml(), $this->images)).'</div>';
    }

    /**
     * How wide an image is shown at the gallery's height.
     */
    private static function sizes(Media $media): ?string
    {
        return $media->width && $media->height
            ? (int) ceil(self::HEIGHT * $media->width / $media->height).'px'
            : null;
    }
}
