<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A link at the bottom of the site's sidebar, to an address or to a post.
 *
 * @property int $id
 * @property string|null $label Optional for posts, which then show their title
 * @property string|null $url An address, when the link is not to a post
 * @property int|null $post_id
 * @property string|null $emoji
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Post|null $post
 */
#[Fillable(['label', 'url', 'post_id', 'emoji', 'position'])]
class Link extends Model
{
    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * What the link says: its label, or the title of its post.
     */
    public function text(): string
    {
        return $this->label ?? $this->post->title ?? '';
    }

    /**
     * Where the link goes, or null while its post is not published. Links to
     * posts follow them when their slug or template changes.
     */
    public function href(): ?string
    {
        if ($this->post_id === null) {
            return $this->url;
        }

        return $this->post?->isPublished() ? $this->post->url() : null;
    }

    /**
     * Whether the link leaves the site.
     */
    public function isExternal(): bool
    {
        return $this->post_id === null
            && preg_match('#^https?://#i', (string) $this->url) === 1
            && parse_url((string) $this->url, PHP_URL_HOST) !== request()->getHost();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }
}
