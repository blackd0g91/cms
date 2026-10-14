<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One of the owner's apps or sites, shown as a card on the home page: its
 * logo, name and a short description over a faded background image, linking
 * out to it. Projects always link elsewhere, never to a post.
 *
 * @property int $id
 * @property string $name
 * @property string $url
 * @property string|null $description
 * @property int|null $logo_id
 * @property int|null $background_id
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Media|null $logo Not in the trash
 * @property-read Media|null $background Not in the trash
 */
#[Fillable(['name', 'url', 'description', 'logo_id', 'background_id', 'position'])]
class Project extends Model
{
    /**
     * @return BelongsTo<Media, $this>
     */
    public function logo(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'logo_id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function background(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'background_id');
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
