<?php

namespace App\Http\Controllers\Site;

use App\Cms\Reminders;
use App\Enums\ReminderRepeat;
use App\Http\Controllers\Controller;
use App\Models\Reminder;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A member's reminders, on their own page and in the panel on the home
 * page. Times are typed in the member's time zone, which their browser sends
 * along (see site.ts).
 */
class ReminderController extends Controller
{
    public function index(Request $request, Reminders $reminders): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('site.reminders.index', [
            'groups' => $reminders->groups($user),
            'reminders' => $reminders,
            'user' => $user,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->reminders()->whereNull('completed_at')->count() >= Reminder::MAX_ACTIVE) {
            throw ValidationException::withMessages(['text' => 'You have '.Reminder::MAX_ACTIVE.' reminders waiting, the most there can be. Finish or delete some first.']);
        }

        $user->reminders()->create($this->validated($request, $user));

        return back()->with('status', 'Reminder added.');
    }

    public function update(Request $request, Reminder $reminder): RedirectResponse
    {
        $user = $this->owner($request, $reminder);

        $reminder->update($this->validated($request, $user));

        return back()->with('status', 'Reminder changed.');
    }

    public function complete(Request $request, Reminder $reminder): RedirectResponse
    {
        $user = $this->owner($request, $reminder);
        $repeats = $reminder->repeat !== null;

        $reminder->complete($user->localTimezone());

        return back()->with('status', $repeats
            ? 'Done. It comes back '.app(Reminders::class)->when($reminder, $user->localTimezone()).'.'
            : 'Done.');
    }

    /**
     * Later: in an hour, or tomorrow at 9:00.
     */
    public function snooze(Request $request, Reminder $reminder): RedirectResponse
    {
        $user = $this->owner($request, $reminder);

        $until = $request->validate(['until' => ['required', Rule::in(['hour', 'tomorrow'])]])['until'];

        $reminder->update(['due_at' => match ($until) {
            'hour' => now()->addHour()->startOfMinute(),
            default => now($user->localTimezone())->addDay()->setTime(9, 0)->utc(),
        }]);

        return back()->with('status', 'Snoozed until '.app(Reminders::class)->when($reminder, $user->localTimezone()).'.');
    }

    public function destroy(Request $request, Reminder $reminder): RedirectResponse
    {
        $this->owner($request, $reminder);

        $reminder->delete();

        return back()->with('status', 'Reminder deleted.');
    }

    /**
     * The member, when the reminder is theirs: anyone else's is as good as
     * missing.
     */
    private function owner(Request $request, Reminder $reminder): User
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($reminder->user_id === $user->id, 404);

        return $user;
    }

    /**
     * The text, time and repeat from the form, with the time turned from the
     * member's time zone into UTC. The time zone the browser sent is kept,
     * so later reminders and emails use it.
     *
     * @return array{text: string, due_at: CarbonImmutable, repeat: ReminderRepeat|null}
     */
    private function validated(Request $request, User $user): array
    {
        $validated = $request->validate([
            'text' => ['required', 'string', 'max:200'],
            'due' => ['required', 'date_format:Y-m-d\TH:i'],
            'repeat' => ['nullable', Rule::enum(ReminderRepeat::class)],
            'timezone' => ['nullable', 'timezone:all'],
        ], [
            'due.required' => 'Pick a day and a time.',
            'due.date_format' => 'Pick a day and a time.',
        ]);

        if (isset($validated['timezone']) && $validated['timezone'] !== $user->timezone) {
            $user->update(['timezone' => $validated['timezone']]);
        }

        $due = CarbonImmutable::createFromFormat('Y-m-d\TH:i', $validated['due'], $user->localTimezone());

        // A minute of leeway, for a form filled in at the turn of a minute.
        if (! $due || $due->lt(now()->subMinute())) {
            throw ValidationException::withMessages(['due' => 'Pick a time from now on.']);
        }

        return [
            'text' => trim($validated['text']),
            'due_at' => $due->utc(),
            'repeat' => isset($validated['repeat']) ? ReminderRepeat::from($validated['repeat']) : null,
        ];
    }
}
