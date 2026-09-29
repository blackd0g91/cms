<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * A label shared by posts of any template.
 *
 * @property int $id
 * @property string $name
 * @property string $slug
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'slug'])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use HasFactory;

    /**
     * @return BelongsToMany<Post, $this>
     */
    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class);
    }

    /**
     * The tags with these names, created when they do not exist yet. Names
     * that only differ in case or accents ("Café", "cafe") are the same tag.
     *
     * @param  array<int, string>  $names
     * @return list<int>
     */
    public static function idsFor(array $names): array
    {
        $ids = [];

        foreach ($names as $name) {
            $name = trim((string) preg_replace('/\s+/', ' ', $name));
            $slug = Str::slug($name);

            if ($slug !== '') {
                $ids[] = self::query()->firstOrCreate(['slug' => $slug], ['name' => $name])->id;
            }
        }

        return array_values(array_unique($ids));
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
