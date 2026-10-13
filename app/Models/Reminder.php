<?php

namespace App\Models;

use App\Enums\ReminderRepeat;
use Carbon\CarbonImmutable;
use Database\Factories\ReminderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something a member wants to be reminded of, at a time. Times are kept in
 * UTC and shown in the member's time zone.
 *
 * @property int $id
 * @property int $user_id
 * @property string $text
 * @property CarbonImmutable $due_at
 * @property ReminderRepeat|null $repeat
 * @property bool $email Whether to email it when it is due
 * @property CarbonImmutable|null $emailed_at When it was last emailed, or passed over
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read User $user
 */
#[Fillable(['text', 'due_at', 'repeat', 'email', 'emailed_at', 'completed_at'])]
class Reminder extends Model
{
    /** @use HasFactory<ReminderFactory> */
    use HasFactory;

    /**
     * Reminders a member can have that are not done yet.
     */
    public const int MAX_ACTIVE = 500;

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Not done yet, the soonest first.
     *
     * @param  Builder<Reminder>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('completed_at')->orderBy('due_at')->orderBy('id');
    }

    /**
     * Due and to be emailed, and not emailed yet for this time: once done
     * or snoozed, it has a new time, which is emailed again.
     *
     * @param  Builder<Reminder>  $query
     */
    public function scopeToEmail(Builder $query): void
    {
        $query->where('email', true)
            ->whereNull('completed_at')
            ->where('due_at', '<=', now())
            ->where(fn (Builder $query) => $query->whereNull('emailed_at')->orWhereColumn('emailed_at', '<', 'due_at'));
    }

    /**
     * Mark it done. A repeating one comes back instead, at its next time
     * after now (skipping the times missed while it was overdue).
     */
    public function complete(string $timezone): void
    {
        if ($this->repeat === null) {
            $this->update(['completed_at' => now()]);

            return;
        }

        $next = $this->due_at->setTimezone($timezone);

        // Bounded, in case of a very old daily reminder.
        for ($i = 0; $i < 5000 && $next->lte(now()); $i++) {
            $next = $this->repeat->after($next);
        }

        $this->update(['due_at' => $next->utc()]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
            'emailed_at' => 'immutable_datetime',
            'email' => 'boolean',
            'repeat' => ReminderRepeat::class,
        ];
    }
}
