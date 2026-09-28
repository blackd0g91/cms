<?php

namespace App\Models;

use App\Enums\FieldType;
use Carbon\CarbonImmutable;
use Database\Factories\TemplateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A template defines the fields a post has and the layout used to display it.
 *
 * @property int $id
 * @property string $name
 * @property string $handle
 * @property string|null $description
 * @property string|null $color Accent color as #rrggbb, or null for an automatic one
 * @property list<array{handle: string, label: string, type: string, required: bool, options: list<string>}> $fields
 * @property string $layout
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'handle', 'description', 'color', 'fields', 'layout'])]
class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory;

    /**
     * Handles that would collide with application routes.
     */
    public const array RESERVED_HANDLES = ['cp', 'up', 'build', 'storage', 'api', 'login', 'logout', 'search'];

    /**
     * Field handles that collide with the variables every layout receives.
     */
    public const array RESERVED_FIELD_HANDLES = ['title', 'slug', 'url', 'published_at', 'template'];

    /**
     * The hue (0-359) of the automatic accent color, used when no color is
     * set. Stepping by the golden angle keeps consecutive templates far apart.
     */
    public function hue(): int
    {
        return (int) fmod(20 + $this->id * 137.508, 360);
    }

    /**
     * CSS variables for the template's accent, for an element with the "hue"
     * class (see resources/css/site.css). The chosen color is reset when
     * there is none, so it is never inherited from an outer template.
     */
    public function accentStyle(): string
    {
        return "--hue: {$this->hue()}; --accent-base: ".($this->color ?? 'initial');
    }

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /**
     * Move post values to renamed field handles, given as [old => new].
     * Renames are applied together, so swapping two handles works.
     *
     * @param  array<string, string>  $renames
     */
    public function renameFieldsInPosts(array $renames): void
    {
        if ($renames === []) {
            return;
        }

        $this->posts()->each(function (Post $post) use ($renames) {
            $original = $post->data;
            $data = array_diff_key($original, $renames);

            foreach ($renames as $old => $new) {
                if (array_key_exists($old, $original)) {
                    $data[$new] = $original[$old];
                }
            }

            // Moving data around is not an edit, so leave updated_at alone.
            $post->timestamps = false;
            $post->setRelation('template', $this);
            $post->update(['data' => $data]);
        });
    }

    /**
     * The field type of each field, keyed by field handle.
     *
     * @return array<string, FieldType>
     */
    public function fieldTypes(): array
    {
        return collect($this->fields)
            ->mapWithKeys(fn (array $field) => [$field['handle'] => FieldType::from($field['type'])])
            ->all();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fields' => 'array',
        ];
    }
}
