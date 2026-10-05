<?php

namespace App\Cms;

use App\Enums\PostStatus;
use App\Models\Post;
use ArrayIterator;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Collection;
use IteratorAggregate;
use Traversable;

/**
 * A posts field value inside a layout. {{ apps }} renders the posts as cards,
 * like the ones in listings, while
 * {{# apps }}<a href="{{ url }}">{{ title }}</a>{{/ apps }} goes through them
 * one at a time (see LinkedPost). Only published posts are shown, in the
 * order they were chosen.
 *
 * @implements IteratorAggregate<int, LinkedPost>
 */
class LinkedPosts implements Htmlable, IteratorAggregate
{
    /**
     * @param  Collection<int, Post>  $posts  With their template and thumbnail loaded
     */
    public function __construct(private Collection $posts) {}

    /**
     * The posts that are published, in order, or null when none are.
     *
     * @param  array<int, mixed>  $ids
     */
    public static function fromIds(array $ids): ?self
    {
        $ids = array_values(array_filter($ids, is_int(...)));

        $posts = Post::query()
            ->with(['template', 'thumbnail'])
            ->whereKey($ids)
            ->where('status', PostStatus::Published)
            ->get()
            ->sortBy(fn (Post $post) => array_search($post->id, $ids, true))
            ->values();

        return $posts->isEmpty() ? null : new self($posts);
    }

    /**
     * @return Traversable<int, LinkedPost>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->posts->map(LinkedPost::fromPost(...))->all());
    }

    public function toHtml(): string
    {
        return view('site.partials.linked-posts', ['posts' => $this->posts])->render();
    }
}
