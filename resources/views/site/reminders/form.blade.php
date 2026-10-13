{{--
    Adding a reminder, or changing one. Expects $user, and optionally
    $reminder (to change it), $compact (the home page's panel) and $idPrefix
    (for more than one form on a page). The browser fills in its time zone,
    and the next full hour when the member's is not known yet (see site.ts).
--}}
@php
    $reminder ??= null;
    $compact ??= false;
    $idPrefix ??= 'reminder';
    $timezone = $user->localTimezone();
    $due = $reminder
        ? $reminder->due_at->setTimezone($timezone)->format('Y-m-d\TH:i')
        : ($user->timezone ? now($timezone)->addHour()->startOfHour()->format('Y-m-d\TH:i') : '');
    $inputClass = 'block w-full min-w-0 rounded-xl border border-line bg-paper px-3 py-2 text-sm placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent-soft focus:outline-none';
@endphp
<form
    method="post"
    action="{{ $reminder ? route('reminders.update', $reminder) : route('reminders.store') }}"
    @class([
        'space-y-3',
        'sm:grid sm:grid-cols-[minmax(0,1fr)_auto_auto_auto] sm:items-end sm:gap-3 sm:space-y-0' => ! $compact,
        // In the home page's panel, a row once the panel is wide enough.
        '@xl:grid @xl:grid-cols-[minmax(0,1fr)_auto_auto_auto] @xl:items-end @xl:gap-3 @xl:space-y-0' => $compact,
    ])
>
    @csrf
    @if ($reminder)
        @method('put')
    @endif
    <input type="hidden" name="timezone" value="{{ $user->timezone }}" data-timezone>

    <div>
        <label for="{{ $idPrefix }}-text" @class(['block text-sm font-medium', 'sr-only' => $compact])>Remind me to</label>
        <input id="{{ $idPrefix }}-text" name="text" type="text" required maxlength="200" value="{{ $reminder ? $reminder->text : '' }}" placeholder="Remind me to…" @class([$inputClass, 'mt-1.5' => ! $compact])>
    </div>

    <div @class(['flex gap-2' => $compact])>
        <label for="{{ $idPrefix }}-due" @class(['block text-sm font-medium', 'sr-only' => $compact])>When</label>
        <input id="{{ $idPrefix }}-due" name="due" type="datetime-local" required value="{{ $due }}" @if ($due === '') data-next-hour @endif @class([$inputClass, 'mt-1.5' => ! $compact])>
    </div>

    <div>
        <label for="{{ $idPrefix }}-repeat" @class(['block text-sm font-medium', 'sr-only' => $compact])>Repeat</label>
        <select id="{{ $idPrefix }}-repeat" name="repeat" @class([$inputClass, 'mt-1.5' => ! $compact])>
            <option value="">Once</option>
            @foreach (\App\Enums\ReminderRepeat::cases() as $repeat)
                <option value="{{ $repeat->value }}" @selected($reminder?->repeat === $repeat)>{{ $repeat->label() }}</option>
            @endforeach
        </select>
    </div>

    <button type="submit" @class(['rounded-full bg-ink px-5 py-2 text-sm font-medium text-paper transition hover:bg-accent', 'w-full @xl:w-auto' => $compact])>
        {{ $reminder ? 'Save' : 'Add' }}
    </button>
</form>
