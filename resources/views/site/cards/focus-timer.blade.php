{{--
    A pomodoro timer (see focus-timer.ts): 25 minutes of focus, then a short
    break, and a longer one after every fourth. The card shows the time and
    starts or pauses it; the dialog has the rest. It keeps going across
    reloads and tabs, and when time is up says so in a toast, and in a
    system notification where those are on. Hidden without the script.
--}}
@php
    $playPause = '<svg data-timer-icon="play" class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5.5v13l10-6.5z"/></svg><svg data-timer-icon="pause" hidden class="size-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><rect x="6.5" y="5" width="4" height="14" rx="1"/><rect x="13.5" y="5" width="4" height="14" rx="1"/></svg>';
@endphp
<section data-focus-timer hidden aria-label="Focus timer" class="flex min-w-0 items-center rounded-2xl border border-line bg-card shadow-sm transition hover:border-accent">
    <button type="button" data-dialog-open="focus-timer" aria-haspopup="dialog" class="flex min-w-0 flex-1 items-center gap-3 rounded-l-2xl p-3 text-left">
        <span class="text-2xl leading-none" aria-hidden="true">🍅</span>
        <span data-timer-time class="font-display text-2xl leading-none font-semibold tabular-nums">25:00</span>
        <span class="min-w-0 flex-1 leading-tight">
            <span data-timer-mode class="block truncate text-sm font-medium">Focus</span>
            <span data-timer-state class="block truncate text-xs text-muted">Ready</span>
        </span>
    </button>
    <span class="h-7 w-px shrink-0 bg-line" aria-hidden="true"></span>
    <button type="button" data-timer-toggle class="mx-1.5 grid size-9 shrink-0 place-items-center rounded-full text-muted transition hover:bg-paper hover:text-ink">
        {!! $playPause !!}
        <span data-timer-toggle-label class="sr-only">Start</span>
    </button>
</section>

<x-site.dialog id="focus-timer" title="Focus timer">
    <div data-focus-timer-panel>
        <div class="flex gap-1 rounded-full border border-line bg-paper p-1 text-sm" role="group" aria-label="What to time">
            @foreach (['focus' => 'Focus', 'short' => 'Short break', 'long' => 'Long break'] as $mode => $label)
                <button type="button" data-timer-choose="{{ $mode }}" aria-pressed="false" class="flex-1 rounded-full px-3 py-1.5 transition hover:text-ink aria-pressed:bg-card aria-pressed:font-medium aria-pressed:text-ink aria-pressed:shadow-sm text-muted">{{ $label }}</button>
            @endforeach
        </div>

        <p data-timer-time class="mt-6 text-center font-display text-7xl leading-none font-semibold tabular-nums">25:00</p>
        <p data-timer-state class="mt-2 text-center text-sm text-muted">Ready</p>

        <div class="mt-6 flex justify-center gap-2">
            <button type="button" data-timer-toggle class="inline-flex items-center gap-2 rounded-full bg-ink px-6 py-2.5 text-sm font-medium text-paper transition hover:bg-accent">
                {!! $playPause !!}
                <span data-timer-toggle-label>Start</span>
            </button>
            <button type="button" data-timer-reset class="rounded-full border border-line px-5 py-2.5 text-sm transition hover:border-accent">Reset</button>
        </div>

        <p class="mt-6 border-t border-line pt-3 text-center text-xs text-muted">
            Focus sessions today: <span data-timer-sessions class="font-medium text-ink">0</span>.
            A long break comes after every fourth.
        </p>
    </div>
</x-site.dialog>
