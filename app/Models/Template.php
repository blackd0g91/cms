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
    public const array RESERVED_HANDLES = ['cp', 'up', 'build', 'storage', 'api', 'login', 'logout', 'search', 'tags', 'feed.xml', 'sitemap.xml', 'robots.txt'];

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
     * The accent as a CSS color: the chosen one, or the automatic one at the
     * site's light-mode lightness. For places outside the site's theme, like
     * the control panel.
     */
    public function accentColor(): string
    {
        return $this->color ?? "oklch(0.58 0.13 {$this->hue()})";
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

        // Old versions move too, so restoring one still fills the right fields.
        PostRevision::query()
            ->whereIn('post_id', $this->posts()->select('id'))
            ->each(function (PostRevision $revision) use ($renames) {
                $revision->update(['data' => self::renameKeys($revision->data, $renames)]);
            });

        $this->posts()->each(function (Post $post) use ($renames) {
            // Moving data around is not an edit, so leave updated_at alone.
            $post->timestamps = false;
            $post->setRelation('template', $this);
            $post->update(['data' => self::renameKeys($post->data, $renames)]);
        });
    }

    /**
     * Convert post values (and old versions) for fields whose type changed,
     * given by new handle as in TemplateRequest::changedFieldTypes().
     *
     * @param  array<string, array{from: FieldType, to: FieldType, options: list<string>}>  $changes
     */
    public function convertFieldTypesInPosts(array $changes): void
    {
        if ($changes === []) {
            return;
        }

        $convert = function (array $data) use ($changes): array {
            foreach ($changes as $handle => ['from' => $from, 'to' => $to, 'options' => $options]) {
                if (array_key_exists($handle, $data)) {
                    $data[$handle] = $to->convertFrom($from, $data[$handle], $options);
                }
            }

            return $data;
        };

        PostRevision::query()
            ->whereIn('post_id', $this->posts()->select('id'))
            ->each(fn (PostRevision $revision) => $revision->update(['data' => $convert($revision->data)]));

        $this->posts()->each(function (Post $post) use ($convert) {
            // Converting values is not an edit, so leave updated_at alone.
            $post->timestamps = false;
            $post->setRelation('template', $this);
            $post->update(['data' => $convert($post->data)]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $renames
     * @return array<string, mixed>
     */
    private static function renameKeys(array $data, array $renames): array
    {
        $renamed = array_diff_key($data, $renames);

        foreach ($renames as $old => $new) {
            if (array_key_exists($old, $data)) {
                $renamed[$new] = $data[$old];
            }
        }

        return $renamed;
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
