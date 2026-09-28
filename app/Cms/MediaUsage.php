<?php

namespace App\Cms;

use App\Enums\FieldType;
use App\Models\Media;
use App\Models\Post;
use Illuminate\Support\Collection;

/**
 * Finds where images are used: post thumbnails, image fields, images inserted into markdown
 * (or any other text) by URL, and the home page intro.
 */
class MediaUsage
{
    public function __construct(private Settings $settings) {}

    /**
     * Usages of each media item, keyed by media id. Posts are scanned once
     * for the whole set.
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

        Post::query()->with('template')->each(function (Post $post) use ($media, &$usages) {
            $imageIds = [...$this->imageFieldIds($post), ...array_filter([$post->thumbnail_id])];
            $text = $this->text($post->data);

            foreach ($media as $item) {
                if (in_array($item->id, $imageIds, true) || str_contains($text, $item->path)) {
                    $usages[$item->id][] = [
                        'label' => "{$post->title} ({$post->template->name})",
                        'post_id' => $post->id,
                        'template_id' => $post->template_id,
                    ];
                }
            }
        });

        $intro = $this->settings->get('home_intro');

        foreach ($media as $item) {
            if (is_string($intro) && str_contains($intro, $item->path)) {
                $usages[$item->id][] = ['label' => 'Home page intro', 'post_id' => null, 'template_id' => null];
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

    /**
     * @return list<int>
     */
    private function imageFieldIds(Post $post): array
    {
        $ids = [];

        foreach ($post->template->fieldTypes() as $handle => $type) {
            if ($type === FieldType::Image && is_int($post->data[$handle] ?? null)) {
                $ids[] = $post->data[$handle];
            }
        }

        return $ids;
    }

    private function text(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_array($value) => implode("\n", array_map($this->text(...), $value)),
            default => '',
        };
    }
}
