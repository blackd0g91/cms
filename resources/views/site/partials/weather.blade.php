{{--
    The weather where the visitor is, once they ask for it (see
    resources/js/weather.ts, which also shows the card: it needs the script).
    A small card with the weather now, opening a dialog with the details and
    the week ahead. Their location stays in their browser, and logged in, in
    their account too, so their other devices can start from it.
--}}
@php($member = auth()->user())
<section
    data-weather
    @if ($member)
        data-location-url="{{ route('account.location') }}"
        data-location-token="{{ csrf_token() }}"
        data-account-location="{{ json_encode($member->location) }}"
    @endif
    hidden
    aria-label="Weather"
    class="grid"
>
    <div data-weather-state="empty" class="flex items-center gap-3 rounded-2xl border border-line bg-card p-3 shadow-sm">
        <span class="text-2xl leading-none" aria-hidden="true">🌤️</span>
        <button type="button" data-weather-locate class="min-w-0 flex-1 text-left text-sm font-medium transition hover:text-accent">
            Show my weather
            <span class="block text-xs font-normal text-muted">{{ $member ? 'Kept in this browser and your account' : 'Your location stays in this browser' }}</span>
        </button>
    </div>

    <p data-weather-state="loading" hidden class="flex items-center gap-3 rounded-2xl border border-line bg-card p-3 text-sm text-muted shadow-sm">
        <span class="size-2 animate-pulse rounded-full bg-accent" aria-hidden="true"></span>
        <span data-weather-loading role="status"></span>
    </p>

    <div data-weather-state="error" hidden class="flex items-center gap-3 rounded-2xl border border-line bg-card p-3 shadow-sm">
        <p data-weather-error role="alert" class="min-w-0 flex-1 text-sm"></p>
        <button type="button" data-weather-retry class="shrink-0 rounded-full border border-line bg-paper px-3 py-1 text-xs transition hover:border-accent hover:text-ink">
            Try again
        </button>
    </div>

    {{-- The weather now, opening the forecast, and a button to look the
         location up again, like when travelling. --}}
    <div data-weather-state="ready" hidden class="flex min-w-0 items-stretch gap-2">
        <button
            type="button"
            data-weather-open
            aria-haspopup="dialog"
            class="group flex min-w-0 flex-1 items-center gap-3 rounded-2xl border border-line bg-card p-3 text-left shadow-sm transition hover:border-accent"
        >
            <span data-weather-icon class="text-3xl leading-none" aria-hidden="true"></span>
            <span data-weather-temperature class="font-display text-2xl leading-none font-semibold tracking-tight"></span>
            <span class="min-w-0 flex-1 leading-tight">
                <span data-weather-label class="block truncate text-sm font-medium"></span>
                <span data-weather-place class="block truncate text-xs text-muted"></span>
            </span>
            <span class="sr-only">Show the forecast</span>
            <svg class="size-4 shrink-0 text-muted transition group-hover:translate-x-0.5 group-hover:text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
        </button>
        <button
            type="button"
            data-weather-locate
            title="Update my location"
            class="grid w-11 shrink-0 place-items-center rounded-2xl border border-line bg-card text-muted shadow-sm transition hover:border-accent hover:text-ink"
        >
            <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><circle cx="12" cy="12" r="7.5"/><path d="M12 2v2.5M12 19.5V22M2 12h2.5M19.5 12H22"/></svg>
            <span class="sr-only">Update my location</span>
        </button>
    </div>

    <dialog
        data-weather-dialog
        aria-labelledby="weather-title"
        class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl border border-line bg-card p-0 text-ink shadow-2xl backdrop:bg-black/40 backdrop:backdrop-blur-sm"
    >
        <div class="p-5 sm:p-6">
            <div class="flex items-start justify-between gap-4">
                <h2 id="weather-title" class="min-w-0 font-mono text-xs tracking-wider text-muted uppercase">
                    <span class="sr-only">Weather in </span><span data-weather-place></span>
                </h2>
                <button type="button" data-weather-close aria-label="Close" class="-m-1.5 grid size-8 shrink-0 place-items-center rounded-full text-muted transition hover:bg-paper hover:text-ink">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6l-12 12M6 6l12 12"/></svg>
                </button>
            </div>

            <div class="mt-3 flex items-center gap-4">
                <span data-weather-icon class="text-5xl leading-none" aria-hidden="true"></span>
                <div>
                    <p class="flex items-baseline gap-2">
                        <span data-weather-temperature class="font-display text-5xl leading-none font-semibold tracking-tight"></span>
                        <span data-weather-label class="font-medium"></span>
                    </p>
                    <p class="mt-1 text-sm text-muted">Feels like <span data-weather-feels></span></p>
                </div>
            </div>

            <dl class="mt-5 grid grid-cols-2 gap-x-6 gap-y-3 rounded-2xl bg-paper p-4 font-mono text-xs">
                <div><dt class="text-muted">High / low</dt><dd data-weather-range class="mt-0.5"></dd></div>
                <div><dt class="text-muted">Rain</dt><dd data-weather-rain class="mt-0.5"></dd></div>
                <div><dt class="text-muted">Wind</dt><dd data-weather-wind class="mt-0.5"></dd></div>
                <div><dt class="text-muted">Sun</dt><dd data-weather-sun class="mt-0.5 whitespace-nowrap"></dd></div>
            </dl>

            <h3 class="mt-6 mb-2 font-mono text-xs tracking-wider text-muted uppercase">Next 7 days</h3>
            <ol data-weather-days class="divide-y divide-line"></ol>
            <template data-weather-day-template>
                <li class="grid grid-cols-[3.5rem_1.75rem_2.5rem_1fr_2.5rem_2.5rem] items-center gap-2 py-2 text-sm">
                    <span data-day-name class="font-medium"></span>
                    <span data-day-icon class="text-lg leading-none" aria-hidden="true"></span>
                    <span data-day-low class="text-right text-muted"></span>
                    <span class="relative h-1.5 rounded-full bg-paper" aria-hidden="true">
                        <span data-day-bar class="absolute inset-y-0 rounded-full bg-accent"></span>
                    </span>
                    <span data-day-high class="font-medium"></span>
                    <span data-day-rain class="text-right font-mono text-xs text-[oklch(0.62_0.12_240)]"></span>
                    <span data-day-label class="sr-only"></span>
                </li>
            </template>

            <div class="mt-5 flex flex-wrap items-center gap-2 border-t border-line pt-4 text-xs">
                <button type="button" data-weather-unit class="rounded-full border border-line px-3 py-1.5 transition hover:border-accent hover:text-ink"></button>
                <button type="button" data-weather-locate class="rounded-full border border-line px-3 py-1.5 transition hover:border-accent hover:text-ink">Update my location</button>
                <button type="button" data-weather-forget class="ml-auto rounded-full px-3 py-1.5 text-muted transition hover:bg-paper hover:text-ink">Forget my location</button>
            </div>
        </div>
    </dialog>
</section>
