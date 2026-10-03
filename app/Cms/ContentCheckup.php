<?php

namespace App\Cms;

use App\Enums\FieldType;
use App\Enums\PostStatus;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Things worth tidying up, for the dashboard. Each check lists a few of the
 * affected items with a link to fix them.
 */
class ContentCheckup
{
    /**
     * Drafts not edited for this many days are considered forgotten.
     */
    public const int STALE_DRAFT_DAYS = 30;

    private const int SHOWN = 8;

    /**
     * @return list<array{key: string, label: string, hint: string, count: int, items: list<array{title: string, detail: string|null, href: string}>}>
     */
    public function run(): array
    {
        return [
            $this->postCheck(
                'no-thumbnail',
                'Posts without a thumbnail',
                'Thumbnails show in listings, beside the title and in link previews.',
                Post::query()->whereNull('thumbnail_id'),
            ),
            $this->postCheck(
                'no-tags',
                'Posts without tags',
                'Tags group related posts across templates.',
                Post::query()->whereDoesntHave('tags'),
            ),
            $this->postCheck(
                'stale-drafts',
                'Drafts untouched for '.self::STALE_DRAFT_DAYS.' days',
                'Publish them, or delete the ones you no longer need.',
                Post::query()->where('status', PostStatus::Draft)->where('updated_at', '<', now()->subDays(self::STALE_DRAFT_DAYS)),
                detail: fn (Post $post) => 'last edited '.$post->updated_at?->diffForHumans(),
            ),
            $this->brokenImages(),
            $this->mediaWithoutAlt(),
        ];
    }

    /**
     * @param  Builder<Post>  $query
     * @param  (callable(Post): string)|null  $detail
     * @return array{key: string, label: string, hint: string, count: int, items: list<array{title: string, detail: string|null, href: string}>}
     */
    private function postCheck(string $key, string $label, string $hint, Builder $query, ?callable $detail = null): array
    {
        $count = (clone $query)->count();

        $posts = $query->with('template:id,name')->latest('updated_at')->limit(self::SHOWN)->get();

        return [
            'key' => $key,
            'label' => $label,
            'hint' => $hint,
            'count' => $count,
            'items' => $this->postItems($posts, $detail),
        ];
    }

    /**
     * Image fields, and images inserted into text, that point at deleted media.
     *
     * @return array{key: string, label: string, hint: string, count: int, items: list<array{title: string, detail: string|null, href: string}>}
     */
    private function brokenImages(): array
    {
        $ids = Media::query()->pluck('id')->flip();
        $paths = Media::query()->pluck('path')->flip();
        $broken = collect();

        Post::query()->with('template')->each(function (Post $post) use ($ids, $paths, $broken) {
            $missing = 0;

            foreach ($post->template->fieldTypes() as $handle => $type) {
                $value = $post->data[$handle] ?? null;

                if ($type === FieldType::Image && is_int($value) && ! $ids->has($value)) {
                    $missing++;
                }
            }

            preg_match_all('#/storage/(media/[A-Za-z0-9]+\.[a-z0-9]+)#i', json_encode($post->data, JSON_UNESCAPED_SLASHES) ?: '', $matches);
            $missing += collect($matches[1])->unique()->reject(fn (string $path) => $paths->has($path))->count();

            if ($missing > 0) {
                $post->setAttribute('missing_images', $missing);
                $broken->push($post);
            }
        });

        return [
            'key' => 'broken-images',
            'label' => 'Posts with missing images',
            'hint' => 'These posts point at images that were deleted or are in the trash, so they show nothing or a broken image.',
            'count' => $broken->count(),
            'items' => $this->postItems(
                $broken->take(self::SHOWN),
                fn (Post $post) => $post->getAttribute('missing_images').' missing',
            ),
        ];
    }

    /**
     * @return array{key: string, label: string, hint: string, count: int, items: list<array{title: string, detail: string|null, href: string}>}
     */
    private function mediaWithoutAlt(): array
    {
        $query = Media::query()->where(fn ($query) => $query->whereNull('alt')->orWhere('alt', ''));

        return [
            'key' => 'no-alt',
            'label' => 'Images without alt text',
            'hint' => 'Alt text describes an image for screen readers and when it can not load.',
            'count' => (clone $query)->count(),
            'items' => array_values($query->latest()->limit(self::SHOWN)->get()
                ->map(fn (Media $media) => [
                    'title' => $media->filename,
                    'detail' => null,
                    'href' => route('cp.media.index'),
                ])
                ->all()),
        ];
    }

    /**
     * @param  Collection<int, Post>  $posts
     * @param  (callable(Post): string)|null  $detail
     * @return list<array{title: string, detail: string|null, href: string}>
     */
    private function postItems(Collection $posts, ?callable $detail): array
    {
        return array_values($posts
            ->map(fn (Post $post) => [
                'title' => $post->title,
                'detail' => $detail ? $detail($post) : $post->template->name,
                'href' => route('cp.templates.posts.edit', [$post->template_id, $post->id]),
            ])
            ->all());
    }
}
