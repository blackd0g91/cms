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
 * @property list<array{handle: string, label: string, type: string, required: bool, options: list<string>}> $fields
 * @property string $layout
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
#[Fillable(['name', 'handle', 'description', 'fields', 'layout'])]
class Template extends Model
{
    /** @use HasFactory<TemplateFactory> */
    use HasFactory;

    /**
     * Handles that would collide with application routes.
     */
    public const array RESERVED_HANDLES = ['cp', 'up', 'build', 'storage', 'api', 'login', 'logout'];

    /**
     * Field handles that collide with the variables every layout receives.
     */
    public const array RESERVED_FIELD_HANDLES = ['title', 'slug', 'url', 'published_at', 'template'];

    /**
     * @return HasMany<Post, $this>
     */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
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
