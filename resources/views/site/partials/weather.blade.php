{{--
    The weather where the visitor is, once they ask for it (see
    resources/js/weather.ts, which also shows the card: it needs the script).
    Their location stays in their browser.
--}}
<section data-weather hidden aria-label="Weather" class="mb-8 rounded-2xl border border-line bg-card p-4 shadow-sm sm:px-6">
    <div data-weather-state="empty" class="flex flex-wrap items-center gap-x-4 gap-y-3">
        <span class="text-3xl" aria-hidden="true">🌤️</span>
        <div class="min-w-0 flex-1">
            <p class="font-medium">The weather where you are</p>
            <p class="text-sm text-muted">Your location is only kept in this browser.</p>
        </div>
        <button type="button" data-weather-locate class="rounded-full border border-line bg-paper px-4 py-2 text-sm transition hover:border-accent hover:text-ink">
            Show my weather
        </button>
    </div>

    <p data-weather-state="loading" hidden class="flex items-center gap-3 text-sm text-muted">
        <span class="size-2 animate-pulse rounded-full bg-accent" aria-hidden="true"></span>
        <span data-weather-loading role="status"></span>
    </p>

    <div data-weather-state="error" hidden class="flex flex-wrap items-center gap-x-4 gap-y-3">
        <p data-weather-error role="alert" class="min-w-0 flex-1 text-sm"></p>
        <button type="button" data-weather-retry class="rounded-full border border-line bg-paper px-4 py-2 text-sm transition hover:border-accent hover:text-ink">
            Try again
        </button>
    </div>

    <div data-weather-state="ready" hidden class="flex flex-wrap items-center gap-x-8 gap-y-4">
        <div class="flex min-w-0 items-center gap-4">
            <span data-weather-icon class="text-4xl leading-none" aria-hidden="true"></span>
            <div class="min-w-0">
                <p class="flex items-baseline gap-2">
                    <button type="button" data-weather-temperature class="font-display text-3xl leading-none font-semibold tracking-tight transition hover:text-accent"></button>
                    <span data-weather-label class="font-medium"></span>
                </p>
                <p class="mt-1 truncate text-sm text-muted">
                    <span data-weather-place></span> · feels like <span data-weather-feels></span>
                </p>
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-x-6 gap-y-1 font-mono text-xs sm:grid-cols-4">
            <div><dt class="text-muted">High / low</dt><dd data-weather-range></dd></div>
            <div><dt class="text-muted">Rain</dt><dd data-weather-rain></dd></div>
            <div><dt class="text-muted">Wind</dt><dd data-weather-wind></dd></div>
            <div><dt class="text-muted">Sun</dt><dd data-weather-sun></dd></div>
        </dl>

        <div class="flex gap-1 sm:ml-auto">
            <button type="button" data-weather-locate title="Update my location" aria-label="Update my location" class="grid size-8 place-items-center rounded-full text-muted transition hover:bg-paper hover:text-ink">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 12m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3"/><path d="M12 12m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/></svg>
            </button>
            <button type="button" data-weather-forget title="Forget my location" aria-label="Forget my location" class="grid size-8 place-items-center rounded-full text-muted transition hover:bg-paper hover:text-ink">
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6l-12 12M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
</section>
