<?php

namespace App\Models;

use App\Cms\Oklch;
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
     * Automatic accents in light mode, and the lightest a chosen color is
     * shown at so it stays readable on paper (see resources/css/site.css).
     */
    private const float ACCENT_LIGHTNESS = 0.58;

    private const float ACCENT_CHROMA = 0.13;

    private const float ACCENT_MAX_LIGHTNESS = 0.68;

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
        return $this->color ?? 'oklch('.self::ACCENT_LIGHTNESS.' '.self::ACCENT_CHROMA." {$this->hue()})";
    }

    /**
     * The accent as the site shows it in light mode, in sRGB, for drawing it
     * (see ShareImage). Chosen colors that are too light are darkened.
     *
     * @return array{int, int, int}
     */
    public function accentRgb(): array
    {
        $color = $this->color === null
            ? new Oklch(self::ACCENT_LIGHTNESS, self::ACCENT_CHROMA, $this->hue())
            : Oklch::fromHex($this->color);

        return $color->withLightness(min($color->lightness, self::ACCENT_MAX_LIGHTNESS))->toRgb();
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

        $this->changeDataInPosts(fn (array $data) => self::renameKeys($data, $renames));
    }

    /**
     * Clear post values under the handles of fields that were just added. A
     * removed field's values stay in posts that were not saved since (and in
     * their history), and a new field starts empty rather than taking them,
     * whatever type it has.
     *
     * @param  list<string>  $handles
     */
    public function clearFieldsInPosts(array $handles): void
    {
        if ($handles === []) {
            return;
        }

        $this->changeDataInPosts(fn (array $data) => array_diff_key($data, array_flip($handles)));
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

        $this->changeDataInPosts($convert);
    }

    /**
     * Change the data of every post, including those in the trash, and of
     * their old versions, so restoring either fills the right fields.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     */
    private function changeDataInPosts(callable $change): void
    {
        PostRevision::query()
            ->whereIn('post_id', $this->posts()->withTrashed()->select('id'))
            ->each(fn (PostRevision $revision) => $revision->update(['data' => $change($revision->data)]));

        $this->posts()->withTrashed()->each(function (Post $post) use ($change) {
            // Changing the fields is not an edit, so leave updated_at alone.
            $post->timestamps = false;
            $post->setRelation('template', $this);
            $post->update(['data' => $change($post->data)]);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, string>  $renames
     * @return array<string, mixed>
     */
    private static function renameKeys(array $data, array $renames): array
    {
        // New handles are cleared too, so a field renamed to the handle of a
        // removed one does not take its values.
        $renamed = array_diff_key($data, $renames, array_flip($renames));

        foreach ($renames as $old => $new) {
            if (array_key_exists($old, $data)) {
                $renamed[$new] = $data[$old];
            }
        }

        return $renamed;
    }

    /**
     * The images chosen in image and gallery fields of a post's data (or a
     * saved version's), by id.
     *
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    public function imageIds(array $data): array
    {
        $ids = [];

        foreach ($this->fieldTypes() as $handle => $type) {
            $ids = match ($type) {
                FieldType::Image => [...$ids, $data[$handle] ?? null],
                FieldType::Gallery => [...$ids, ...array_values((array) ($data[$handle] ?? []))],
                default => $ids,
            };
        }

        return array_values(array_unique(array_filter($ids, is_int(...))));
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
