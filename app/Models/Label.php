<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One of a member's own labels, with a color, for grouping their notes and
 * reminders. Only ever shown to them. (The posts' tags are App\Models\Tag.)
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $color A hex color, like #c2410c
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable(['name', 'color'])]
class Label extends Model
{
    /**
     * The most labels a member can keep.
     */
    public const int MAX = 30;

    /**
     * Colors to pick from quickly, besides any other.
     */
    public const array COLORS = ['#dc2626', '#ea580c', '#ca8a04', '#16a34a', '#0d9488', '#2563eb', '#7c3aed', '#db2777', '#64748b'];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<Note, $this>
     */
    public function notes(): BelongsToMany
    {
        return $this->belongsToMany(Note::class);
    }

    /**
     * @return BelongsToMany<Reminder, $this>
     */
    public function reminders(): BelongsToMany
    {
        return $this->belongsToMany(Reminder::class);
    }
}
