<?php

namespace App\Models;

use App\Cms\Markdown;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

/**
 * A member's note: markdown, with an optional title, only ever shown to
 * them. Pinned ones come first.
 *
 * @property int $id
 * @property int $user_id
 * @property string|null $title
 * @property string $body Markdown
 * @property CarbonImmutable|null $pinned_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable(['title', 'body', 'pinned_at'])]
class Note extends Model
{
    /**
     * The most notes a member can keep.
     */
    public const int MAX = 500;

    /**
     * The longest a note can be, in characters.
     */
    public const int MAX_LENGTH = 20_000;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Pinned first (the latest pinned on top), then the latest changed.
     *
     * @param  Builder<Note>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderByRaw('pinned_at is null')
            ->orderByDesc('pinned_at')
            ->orderByDesc('updated_at')
            ->orderByDesc('id');
    }

    public function html(): HtmlString
    {
        return new HtmlString(app(Markdown::class)->render($this->body));
    }

    /**
     * The note as plain text, cut to $length characters: for short lists.
     * Paragraphs, list items and the like are set apart with a dot, so they
     * do not run together.
     */
    public function preview(int $length = 160): string
    {
        $html = preg_replace('#</(p|li|h[1-6]|blockquote|pre|tr|dd|dt)>#i', "$0\n", $this->html()->toHtml()) ?? '';

        $parts = collect(explode("\n", html_entity_decode(strip_tags($html))))
            ->map(fn (string $part) => Str::squish($part))
            ->filter(fn (string $part) => $part !== '');

        return Str::limit($parts->implode(' · '), $length);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pinned_at' => 'immutable_datetime',
        ];
    }
}
