{{--
    One reminder: a button to mark it done, what and when, and on the
    reminders page, snoozing, changing and deleting it. Expects $reminder,
    $reminders (App\Cms\Reminders), $user and optionally $compact.
--}}
@php($compact ??= false)
<li class="flex items-start gap-3 py-2.5">
    <form method="post" action="{{ route('reminders.complete', $reminder) }}" class="shrink-0">
        @csrf
        <button type="submit" title="Mark done" class="group mt-0.5 grid size-5 place-items-center rounded-full border-2 border-line transition hover:border-accent">
            <svg class="size-3 text-accent opacity-0 transition group-hover:opacity-100" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m5 12 5 5 9-10"/></svg>
            <span class="sr-only">Mark done: {{ $reminder->text }}</span>
        </button>
    </form>

    <div class="min-w-0 flex-1">
        <p class="text-sm break-words">{{ $reminder->text }}</p>
        <p class="mt-0.5 flex items-center gap-1.5 font-mono text-xs text-muted">
            {{ $reminders->when($reminder, $user->localTimezone()) }}
            @if ($reminder->repeat)
                <span title="{{ $reminder->repeat->label() }}">· ↻ {{ Str::lower($reminder->repeat->label()) }}</span>
            @endif
        </p>

        @unless ($compact)
            <details class="mt-1.5">
                <summary class="inline-flex cursor-pointer list-none gap-3 text-xs text-muted [&::-webkit-details-marker]:hidden">
                    <span class="underline-offset-4 hover:text-ink hover:underline">Change</span>
                </summary>
                <div class="mt-2 rounded-2xl border border-line bg-paper/60 p-3">
                    @include('site.reminders.form', ['reminder' => $reminder, 'idPrefix' => 'reminder-'.$reminder->id])
                </div>
            </details>
        @endunless
    </div>

    @unless ($compact)
        <div class="flex shrink-0 items-center gap-1">
            <details data-popover class="relative">
                <summary title="Snooze" class="grid size-8 cursor-pointer list-none place-items-center rounded-full text-muted transition hover:bg-paper hover:text-ink [&::-webkit-details-marker]:hidden">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="13" r="8"/><path d="M12 9v4l2 2M5 3 2 6M22 6l-3-3"/></svg>
                    <span class="sr-only">Snooze</span>
                </summary>
                <div class="absolute right-0 z-20 mt-1 w-44 rounded-xl border border-line bg-card p-1 text-sm shadow-xl">
                    @foreach (['hour' => 'In an hour', 'tomorrow' => 'Tomorrow at 9:00'] as $until => $label)
                        <form method="post" action="{{ route('reminders.snooze', $reminder) }}">
                            @csrf
                            <input type="hidden" name="until" value="{{ $until }}">
                            <button type="submit" class="w-full rounded-lg px-3 py-2 text-left transition hover:bg-paper">{{ $label }}</button>
                        </form>
                    @endforeach
                </div>
            </details>
            <form method="post" action="{{ route('reminders.destroy', $reminder) }}">
                @csrf
                @method('delete')
                <button type="submit" title="Delete" class="grid size-8 place-items-center rounded-full text-muted transition hover:bg-paper hover:text-red-600">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5.5 7l1 12a2 2 0 0 0 2 1.8h7a2 2 0 0 0 2-1.8l1-12M9 7V4.5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1V7"/></svg>
                    <span class="sr-only">Delete: {{ $reminder->text }}</span>
                </button>
            </form>
        </div>
    @endunless
</li>
