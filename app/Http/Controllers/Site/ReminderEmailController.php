<?php

namespace App\Http\Controllers\Site;

use App\Cms\Reminders;
use App\Http\Controllers\Controller;
use App\Models\Reminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The links in a reminder's email (see ReminderEmail): mark it done, snooze
 * it, or stop its emails, without logging in. The link opens a page to
 * confirm, as email apps open links on their own to check them; the button
 * there does it.
 */
class ReminderEmailController extends Controller
{
    private const array ACTIONS = [
        'done' => 'Mark as done',
        'snooze' => 'Snooze for an hour',
        'stop' => 'Stop emails for this reminder',
    ];

    public function show(Reminder $reminder, string $action, Reminders $reminders): View
    {
        abort_unless(isset(self::ACTIONS[$action]), 404);

        return view('site.reminders.email-action', [
            'reminder' => $reminder,
            'action' => $action,
            'label' => self::ACTIONS[$action],
            'when' => $reminders->when($reminder, $reminder->user->localTimezone()),
        ]);
    }

    public function update(Reminder $reminder, string $action): RedirectResponse
    {
        abort_unless(isset(self::ACTIONS[$action]), 404);

        if ($reminder->completed_at !== null) {
            return redirect()->route('home')->with('status', 'That reminder is already done.');
        }

        $status = match ($action) {
            'done' => $this->done($reminder),
            'snooze' => $this->snooze($reminder),
            default => $this->stop($reminder),
        };

        return redirect()->route('home')->with('status', $status);
    }

    private function done(Reminder $reminder): string
    {
        $reminder->complete($reminder->user->localTimezone());

        return $reminder->repeat ? 'Done. It comes back '.app(Reminders::class)->when($reminder, $reminder->user->localTimezone()).'.' : 'Done.';
    }

    private function snooze(Reminder $reminder): string
    {
        $reminder->update(['due_at' => now()->addHour()->startOfMinute()]);

        return 'Snoozed for an hour. It will be emailed again then.';
    }

    private function stop(Reminder $reminder): string
    {
        $reminder->update(['email' => false]);

        return 'No more emails for that reminder. It stays on your reminders page.';
    }
}
