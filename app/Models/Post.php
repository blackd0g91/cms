<?php

namespace App\Models;

use App\Cms\ShareImage;
use App\Enums\FieldType;
use App\Enums\PostStatus;
use Carbon\CarbonImmutable;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $template_id
 * @property int|null $author_id
 * @property string $title
 * @property string $slug
 * @property PostStatus $status
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $pinned_at
 * @property array<string, mixed> $data
 * @property string|null $search_index
 * @property int|null $thumbnail_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Template $template
 * @property-read User|null $author
 * @property-read Media|null $thumbnail
 * @property-read Collection<int, Media> $media
 * @property-read Collection<int, PostRevision> $revisions
 */
#[Fillable(['title', 'slug', 'status', 'published_at', 'pinned_at', 'data', 'thumbnail_id', 'author_id'])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        // saving() also runs for saveQuietly(), which the backfill migration relies on.
        static::saving(fn (Post $post) => $post->search_index = $post->buildSearchIndex());

        static::saved(fn (Post $post) => $post->syncMedia());

        static::deleted(fn (Post $post) => app(ShareImage::class)->forget($post));
    }

    /**
     * The searchable text: title, field text and tag names.
     */
    public function buildSearchIndex(): string
    {
        $tags = $this->exists ? $this->tags()->pluck('name')->implode(' ') : '';

        return self::normalizeForSearch($this->title.' '.$this->plainText().' '.$tags);
    }

    /**
     * Set the post's tags by name, creating new ones, and update the search
     * index to include them.
     *
     * @param  array<int, string>  $names
     */
    public function syncTags(array $names): void
    {
        $this->tags()->sync(Tag::idsFor($names));

        $this->forceFill(['search_index' => $this->buildSearchIndex()])->saveQuietly();
    }

    /**
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->orderBy('name');
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

    /**
     * A short plain-text summary, for page descriptions, link previews and feeds.
     */
    public function summary(int $length = 180): string
    {
        return Str::limit($this->plainText(), $length);
    }

    /**
     * Rough reading time, at 200 words a minute.
     */
    public function readingMinutes(): int
    {
        return max(1, (int) ceil(str_word_count($this->plainText()) / 200));
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
                '/\{\{\s*recipe-amount\s*:\s*([^{}\n]*?)\s*\}\}/', // recipe amounts, keeping the amount
                '/\{\{[^{}\n]*\}\}/',            // other {{ widgets }}
                '/^\s*\[!\w+\][+-]?/m',         // callout markers
                '/\[\[([^\[\]\n]+)\]\]/',       // [[keys]], keeping the key
            ],
            ['', '$1', '$1', '', '', '$1', '', '', '$1'],
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
     * Who wrote it, shown on the site. Posts can have none.
     *
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
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

    /**
     * Every image the post uses, kept up to date by syncMedia().
     *
     * @return BelongsToMany<Media, $this>
     */
    public function media(): BelongsToMany
    {
        return $this->belongsToMany(Media::class);
    }

    /**
     * Record which images the post uses: its thumbnail, image fields, and
     * images referenced by URL in any text (like markdown).
     */
    public function syncMedia(): void
    {
        $ids = [$this->thumbnail_id];

        foreach ($this->template->fieldTypes() as $handle => $type) {
            if ($type === FieldType::Image) {
                $ids[] = $this->data[$handle] ?? null;
            }
        }

        preg_match_all('#media/[A-Za-z0-9]+\.[a-z0-9]+#i', json_encode($this->data, JSON_UNESCAPED_SLASHES) ?: '', $paths);

        $ids = [
            ...array_filter($ids, is_int(...)),
            ...Media::query()->whereIn('path', array_unique($paths[0]))->pluck('id')->all(),
        ];

        $this->media()->sync(Media::query()->whereKey(array_unique($ids))->pluck('id'));
    }

    /**
     * Other published posts to read next: those sharing the most tags first
     * (from any template), then ones from the same template, newest first.
     * Posts with neither in common are left out.
     *
     * @return Collection<int, Post>
     */
    public function related(int $limit = 3): Collection
    {
        $tagIds = $this->tags->modelKeys();

        return self::query()
            ->with(['template', 'thumbnail'])
            ->where('status', PostStatus::Published)
            ->whereKeyNot($this->id)
            ->where(fn (Builder $query) => $query
                ->where('template_id', $this->template_id)
                ->when($tagIds !== [], fn (Builder $query) => $query->orWhereHas('tags', fn (Builder $tags) => $tags->whereKey($tagIds))))
            ->withCount(['tags as shared_tags_count' => fn (Builder $tags) => $tags->whereKey($tagIds)])
            ->orderByDesc('shared_tags_count')
            ->orderByRaw('template_id = ? desc', [$this->template_id])
            ->orderByDesc('published_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Saved versions, newest first.
     *
     * @return HasMany<PostRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(PostRevision::class)->latest('created_at')->latest('id');
    }

    /**
     * How many versions are kept per post. Older ones are deleted.
     */
    public const int KEPT_REVISIONS = 50;

    /**
     * Store the post as it is now in its history, unless nothing changed
     * since the last version.
     */
    public function recordRevision(?User $user = null): void
    {
        $snapshot = [
            'title' => $this->title,
            'slug' => $this->slug,
            'status' => $this->status,
            'thumbnail_id' => $this->thumbnail_id,
            'data' => $this->data,
        ];

        $latest = $this->revisions()->first();

        if ($latest && $latest->only(array_keys($snapshot)) == $snapshot) {
            return;
        }

        $this->revisions()->create([...$snapshot, 'user_id' => $user?->id, 'created_at' => now()]);

        $this->revisions()->skip(self::KEPT_REVISIONS)->take(PHP_INT_MAX)->pluck('id')
            ->whenNotEmpty(fn ($ids) => PostRevision::query()->whereKey($ids)->delete());
    }

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published;
    }

    /**
     * The post as a row in control panel lists. Load its template and author.
     *
     * @return array<string, mixed>
     */
    public function toListItem(): array
    {
        return [
            ...$this->only(['id', 'title', 'slug', 'status', 'published_at', 'updated_at']),
            'pinned' => $this->isPinned(),
            'template' => $this->template->only(['id', 'name']),
            'author' => $this->author?->name,
            'url' => $this->url(),
        ];
    }

    public function isPinned(): bool
    {
        return $this->pinned_at !== null;
    }

    /**
     * Pinned posts first (the most recently pinned on top), then the newest.
     *
     * @param  Builder<Post>  $query
     */
    public function scopePinnedFirst(Builder $query): void
    {
        $query->orderByRaw('pinned_at is null')
            ->orderByDesc('pinned_at')
            ->orderByDesc('published_at');
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
            'pinned_at' => 'datetime',
            'data' => 'array',
        ];
    }
}
