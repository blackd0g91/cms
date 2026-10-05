<?php

namespace App\Cms;

use App\Models\Post;
use Illuminate\Contracts\Support\Htmlable;

/**
 * One post of a posts field, inside a layout: {{ title }}, {{ url }},
 * {{ summary }}, {{ published_at }}, {{ template.name }} and {{ thumbnail }}
 * (with the same parts as an image field). On its own, as {{ . }}, it is a
 * link to the post.
 *
 * Only these are available, not the post itself, so a layout can not reach
 * its unpublished data or call its methods.
 */
class LinkedPost implements Htmlable
{
    /**
     * @param  array{name: string, handle: string}  $template
     */
    public function __construct(
        public readonly string $title,
        public readonly string $url,
        public readonly ?string $summary,
        public readonly ?string $published_at,
        public readonly array $template,
        public readonly ?Image $thumbnail,
    ) {}

    /**
     * From a post with its template and thumbnail loaded.
     */
    public static function fromPost(Post $post): self
    {
        return new self(
            $post->title,
            $post->url(),
            $post->summary,
            $post->published_at?->format('F j, Y'),
            ['name' => $post->template->name, 'handle' => $post->template->handle],
            $post->thumbnail ? Image::fromMedia($post->thumbnail) : null,
        );
    }

    public function toHtml(): string
    {
        return '<a href="'.e($this->url).'">'.e($this->title).'</a>';
    }
}
