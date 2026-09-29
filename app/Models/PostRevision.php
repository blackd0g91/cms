<?php

namespace App\Models;

use App\Enums\PostStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A saved version of a post, for its history.
 *
 * @property int $id
 * @property int $post_id
 * @property int|null $user_id
 * @property string $title
 * @property string $slug
 * @property PostStatus $status
 * @property int|null $thumbnail_id
 * @property array<string, mixed> $data
 * @property CarbonImmutable $created_at
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'title', 'slug', 'status', 'thumbnail_id', 'data', 'created_at'])]
#[WithoutTimestamps]
class PostRevision extends Model
{
    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'data' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
