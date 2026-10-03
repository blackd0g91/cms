<?php

namespace App\Cms;

use App\Models\Media;
use App\Models\Post;
use Illuminate\Support\Collection;

/**
 * Finds where images are used: post thumbnails, image fields, images inserted
 * into markdown (or any other text) by URL, the home page intro, and the site
 * logo and icon. Posts in the trash count too, as they can be restored.
 */
class MediaUsage
{
    public function __construct(private Settings $settings) {}

    /**
     * Usages of each media item, keyed by media id.
     *
     * @param  Collection<int, Media>  $media
     * @return array<int, list<array{label: string, post_id: int|null, template_id: int|null}>>
     */
    public function forMedia(Collection $media): array
    {
        $usages = $media->mapWithKeys(fn (Media $item) => [$item->id => []])->all();

        if ($media->isEmpty()) {
            return $usages;
        }

        $ids = $media->pluck('id')->all();

        $posts = Post::withTrashed()
            ->with(['template:id,name', 'media' => fn ($query) => $query->withTrashed()->whereKey($ids)->select('media.id')])
            ->whereHas('media', fn ($query) => $query->withTrashed()->whereKey($ids))
            ->orderBy('title')
            ->get(['id', 'template_id', 'title', 'deleted_at']);

        foreach ($posts as $post) {
            foreach ($post->media as $item) {
                // A post in the trash can not be opened, so it gets no link.
                $usages[$item->id][] = $post->trashed() ? [
                    'label' => "{$post->title} ({$post->template->name}, in the trash)",
                    'post_id' => null,
                    'template_id' => null,
                ] : [
                    'label' => "{$post->title} ({$post->template->name})",
                    'post_id' => $post->id,
                    'template_id' => $post->template_id,
                ];
            }
        }

        $intro = $this->settings->get('home_intro');

        foreach ($media as $item) {
            if (is_string($intro) && str_contains($intro, $item->path)) {
                $usages[$item->id][] = ['label' => 'Home page intro', 'post_id' => null, 'template_id' => null];
            }

            if ($this->settings->get('logo_id') === $item->id) {
                $usages[$item->id][] = ['label' => 'Site logo', 'post_id' => null, 'template_id' => null];
            }

            if ($this->settings->get('favicon_id') === $item->id) {
                $usages[$item->id][] = ['label' => 'Site icon', 'post_id' => null, 'template_id' => null];
            }
        }

        return $usages;
    }

    /**
     * @return list<array{label: string, post_id: int|null, template_id: int|null}>
     */
    public function for(Media $media): array
    {
        return $this->forMedia(collect([$media]))[$media->id];
    }
}
