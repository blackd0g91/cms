<?php

namespace App\Models;

use App\Enums\FieldType;
use App\Enums\PostStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $template_id
 * @property string $title
 * @property string $slug
 * @property PostStatus $status
 * @property CarbonImmutable|null $published_at
 * @property array<string, mixed> $data
 * @property string|null $search_index
 * @property int|null $thumbnail_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Template $template
 * @property-read Media|null $thumbnail
 */
#[Fillable(['title', 'slug', 'status', 'published_at', 'data', 'thumbnail_id'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // saving() also runs for saveQuietly(), which the backfill migration relies on.
        static::saving(function (Post $post) {
            $post->search_index = self::normalizeForSearch($post->title.' '.$post->plainText());
        });
    }

    /**
     * Lowercase and strip accents, so "pão" matches "pao" and vice versa.
     */
    public static function normalizeForSearch(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    /**
     * Posts containing every word of the query.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeSearch(Builder $query, string $terms): void
    {
        foreach (self::searchTerms($terms) as $term) {
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

            $query->whereRaw("search_index like ? escape '!'", ["%{$escaped}%"]);
        }
    }

    /**
     * @return list<string>
     */
    public static function searchTerms(string $terms): array
    {
        return array_values(array_filter(
            preg_split('/\s+/', self::normalizeForSearch($terms)) ?: [],
            fn (string $term) => $term !== '',
        ));
    }

    /**
     * The readable text of the post's fields, with markdown syntax removed.
     */
    public function plainText(): string
    {
        $parts = [];

        foreach ($this->template->fieldTypes() as $handle => $type) {
            $value = $this->data[$handle] ?? null;

            $parts[] = match ($type) {
                FieldType::Text, FieldType::Textarea, FieldType::Select => is_string($value) ? $value : '',
                FieldType::Markdown => is_string($value) ? self::stripMarkdown($value) : '',
                FieldType::List => implode(' ', array_filter((array) $value, is_string(...))),
                default => '',
            };
        }

        return trim((string) preg_replace('/\s+/', ' ', implode(' ', $parts)));
    }

    private static function stripMarkdown(string $markdown): string
    {
        return (string) preg_replace(
            [
                '/^```.*$/m',                 // code fence lines
                '/!\[([^\]]*)\]\([^)]*\)/',  // images, keeping alt text
                '/\[([^\]]*)\]\([^)]*\)/',   // links, keeping their text
                '/^\s{0,3}(#{1,6}|>|[-*+]|\d+\.)\s+/m', // headings, quotes, list markers
                '/[*~`|]+/',                  // emphasis, code and table characters
            ],
            ['', '$1', '$1', '', ''],
            $markdown,
        );
    }

    /**
     * @return BelongsTo<Template, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * The main image, shown in listings and next to the title.
     *
     * @return BelongsTo<Media, $this>
     */
    public function thumbnail(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published;
    }

    /**
     * The public URL of the post.
     */
    public function url(): string
    {
        return route('site.post', [$this->template, $this]);
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
            'published_at' => 'datetime',
            'data' => 'array',
        ];
    }
}
