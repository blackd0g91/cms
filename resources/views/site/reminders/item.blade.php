{{--
    One reminder: a button to mark it done, what and when, and unless
    compact, buttons to change it (in a dialog), snooze it and delete it. Expects $reminder,
    $reminders (App\Cms\Reminders), $user and optionally $compact.
--}}
@php($compact ??= false)
{{-- The data-reminder attributes let a page tell when it comes due (see reminders.ts). --}}
<li
    class="flex items-start gap-3 py-2.5"
    data-reminder="{{ $reminder->id }}"
    data-reminder-due="{{ $reminder->due_at->toIso8601String() }}"
    data-reminder-text="{{ $reminder->text }}"
    data-reminder-done="{{ route('reminders.complete', $reminder) }}"
    data-reminder-snooze="{{ route('reminders.snooze', $reminder) }}"
>
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
            <span data-reminder-when>{{ $reminders->when($reminder, $user->localTimezone()) }}</span>
            @if ($reminder->repeat)
                <span title="{{ $reminder->repeat->label() }}">· ↻ {{ Str::lower($reminder->repeat->label()) }}</span>
            @endif
            @if ($reminder->email)
                <span title="Emailed when it is due">· ✉<span class="sr-only"> emailed</span></span>
            @endif
        </p>
        @if ($reminder->labels->isNotEmpty())
            <p class="mt-1 flex flex-wrap gap-1">
                @foreach ($reminder->labels as $label)
                    @include('site.labels.chip', ['label' => $label, 'href' => route('reminders.index', ['label' => $label->id])])
                @endforeach
            </p>
        @endif

    </div>

    @unless ($compact)
        <div class="flex shrink-0 items-center gap-1">
            <button type="button" data-dialog-open="edit-reminder-{{ $reminder->id }}" aria-haspopup="dialog" title="Change" class="grid size-8 place-items-center rounded-full text-muted transition hover:bg-paper hover:text-ink">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 20h4L19 9a2.8 2.8 0 0 0-4-4L4 16z"/><path d="m13.5 6.5 4 4"/></svg>
                <span class="sr-only">Change: {{ $reminder->text }}</span>
            </button>
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

        {{-- Opened again when its form comes back with an error. --}}
        <x-site.dialog :id="'edit-reminder-'.$reminder->id" title="Change reminder" :open="old('editing') === 'reminder-'.$reminder->id">
            @if (old('editing') === 'reminder-'.$reminder->id && $errors->any())
                <p class="mb-3 text-sm text-red-600 dark:text-red-400">{{ $errors->first() }}</p>
            @endif
            @include('site.reminders.form', ['reminder' => $reminder, 'stacked' => true, 'idPrefix' => 'reminder-'.$reminder->id])
        </x-site.dialog>
    @endunless
</li>
