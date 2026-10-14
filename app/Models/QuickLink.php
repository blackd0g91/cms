<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of a member's own links, shown as a tile on their page: a site they
 * open often. Only ever shown to them.
 *
 * @property int $id
 * @property int $user_id
 * @property string $label
 * @property string $url
 * @property string|null $emoji
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable(['label', 'url', 'emoji', 'position'])]
class QuickLink extends Model
{
    /**
     * The most links a member can keep.
     */
    public const int MAX = 30;

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
            'position' => 'integer',
        ];
    }
}
