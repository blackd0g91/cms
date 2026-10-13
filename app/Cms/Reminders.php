<?php

namespace App\Cms;

use App\Models\Reminder;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * A member's reminders as the site shows them: grouped by when they are
 * due, in the member's time zone.
 */
class Reminders
{
    /**
     * The reminders not done yet, in groups (Overdue, Today, Tomorrow, This
     * week, Later), leaving out empty ones. With $until, only those due
     * before then.
     *
     * @return list<array{label: string, reminders: list<Reminder>}>
     */
    public function groups(User $user, ?CarbonImmutable $until = null, ?int $limit = null): array
    {
        $reminders = $user->reminders()
            ->active()
            ->when($until, fn ($query, CarbonImmutable $until) => $query->where('due_at', '<', $until->utc()))
            ->when($limit, fn ($query, int $limit) => $query->limit($limit))
            ->get();

        $groups = [];

        foreach ($reminders as $reminder) {
            $groups[$this->group($reminder, $user->localTimezone())][] = $reminder;
        }

        return array_map(
            fn (string $label) => ['label' => $label, 'reminders' => $groups[$label]],
            array_values(array_filter(['Overdue', 'Today', 'Tomorrow', 'This week', 'Later'], fn (string $label) => isset($groups[$label]))),
        );
    }

    /**
     * The end of the week ahead for the member, as the "This week" group
     * counts it: what the member's own page shows.
     */
    public function weekAhead(User $user): CarbonImmutable
    {
        return now($user->localTimezone())->addDays(7)->startOfDay();
    }

    /**
     * When it is due, for people: "09:00" today and tomorrow, with the day
     * in the week ahead ("Thu 09:00"), and the date further off or overdue.
     */
    public function when(Reminder $reminder, string $timezone): string
    {
        $due = $reminder->due_at->setTimezone($timezone);

        return match ($this->group($reminder, $timezone)) {
            'Today', 'Tomorrow' => $due->format('H:i'),
            'This week' => $due->format('D H:i'),
            default => $due->format($due->year === now($timezone)->year ? 'M j, H:i' : 'M j Y, H:i'),
        };
    }

    private function group(Reminder $reminder, string $timezone): string
    {
        $now = now($timezone);
        $due = $reminder->due_at->setTimezone($timezone);

        return match (true) {
            $due->lte($now) => 'Overdue',
            $due->isSameDay($now) => 'Today',
            $due->isSameDay($now->addDay()) => 'Tomorrow',
            $due->lt($now->addDays(7)->startOfDay()) => 'This week',
            default => 'Later',
        };
    }
}
