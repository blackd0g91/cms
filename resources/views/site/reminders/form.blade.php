{{--
    Adding a reminder, or changing one. Expects $user, and optionally
    $reminder (to change it), $stacked (the fields one under the other, as in
    the home page's dialog, rather than in a row) and $idPrefix (for more than
    one form on a page). The browser fills in its time zone, and the next full
    hour when the member's is not known yet (see site.ts).
--}}
@php
    $reminder ??= null;
    $stacked ??= false;
    $idPrefix ??= 'reminder';
    $timezone = $user->localTimezone();
    // A new reminder keeps what was typed when it is sent back with an error.
    $due = $reminder
        ? $reminder->due_at->setTimezone($timezone)->format('Y-m-d\TH:i')
        : old('due', $user->timezone ? now($timezone)->addHour()->startOfHour()->format('Y-m-d\TH:i') : '');
    $inputClass = 'mt-1.5 block w-full min-w-0 rounded-xl border border-line bg-paper px-3 py-2 text-sm placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent-soft focus:outline-none';
@endphp
<form
    method="post"
    action="{{ $reminder ? route('reminders.update', $reminder) : route('reminders.store') }}"
    @class([
        'space-y-4' => $stacked,
        'space-y-3 sm:grid sm:grid-cols-[minmax(0,1fr)_auto_auto_auto] sm:items-end sm:gap-3 sm:space-y-0' => ! $stacked,
    ])
>
    @csrf
    @if ($reminder)
        @method('put')
    @endif
    <input type="hidden" name="timezone" value="{{ $user->timezone }}" data-timezone>

    <div>
        <label for="{{ $idPrefix }}-text" class="block text-sm font-medium">Remind me to</label>
        <input id="{{ $idPrefix }}-text" name="text" type="text" required maxlength="200" value="{{ $reminder ? $reminder->text : old('text') }}" placeholder="Call the dentist…" class="{{ $inputClass }}">
    </div>

    <div @class(['grid grid-cols-[minmax(0,1fr)_auto] gap-3' => $stacked, 'contents' => ! $stacked])>
        <div>
            <label for="{{ $idPrefix }}-due" class="block text-sm font-medium">When</label>
            <input id="{{ $idPrefix }}-due" name="due" type="datetime-local" required value="{{ $due }}" @if ($due === '') data-next-hour @endif class="{{ $inputClass }}">
        </div>

        <div>
            <label for="{{ $idPrefix }}-repeat" class="block text-sm font-medium">Repeat</label>
            <select id="{{ $idPrefix }}-repeat" name="repeat" class="{{ $inputClass }}">
                <option value="">Once</option>
                @foreach (\App\Enums\ReminderRepeat::cases() as $repeat)
                    <option value="{{ $repeat->value }}" @selected(($reminder?->repeat?->value ?? old('repeat')) === $repeat->value)>{{ $repeat->label() }}</option>
                @endforeach
            </select>
        </div>
    </div>

    {{-- Off unless chosen: the site can only send so many emails a day. --}}
    <label @class(['flex items-center gap-2 text-sm', 'sm:order-last sm:col-span-4' => ! $stacked])>
        <input type="checkbox" name="email" value="1" @checked($reminder ? $reminder->email : old('email')) class="size-4 shrink-0 accent-(--accent)">
        <span>Email me when it's due <span class="text-xs whitespace-nowrap text-muted">(up to {{ config('reminders.emails_per_member') }} a day)</span></span>
    </label>

    <button type="submit" @class(['rounded-full bg-ink px-5 py-2 text-sm font-medium text-paper transition hover:bg-accent', 'w-full' => $stacked])>
        {{ $reminder ? 'Save' : 'Add' }}
    </button>
</form>
