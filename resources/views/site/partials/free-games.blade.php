{{--
    Games given away free to keep right now (see app/Cms/FreeGames.php): a
    small card with how many there are, opening a dialog listing them, which
    can be narrowed down by device (see resources/js/filters.ts). Without the
    script the card links to GamerPower. Expects $games, not empty.
--}}
@php
    $count = count($games);
    $devices = collect($games)->pluck('devices')->flatten()->unique()->values();
    $deviceOrder = ['PC', 'PlayStation', 'Xbox', 'Switch', 'Mobile', 'VR'];
    $devices = $devices->sortBy(fn ($device) => array_search($device, $deviceOrder))->values();
@endphp

<a
    href="https://www.gamerpower.com/"
    data-dialog-open="free-games"
    aria-haspopup="dialog"
    class="group flex items-center gap-3 rounded-2xl border border-line bg-card p-3 shadow-sm transition hover:border-accent"
>
    {{-- The first few games' pictures, fanned out. --}}
    <span class="relative h-10 w-16 shrink-0" aria-hidden="true">
        @foreach (array_slice($games, 0, 3) as $game)
            <img
                src="{{ $game['image'] }}"
                alt=""
                loading="lazy"
                class="absolute top-1/2 h-8 w-12 -translate-y-1/2 rounded-md object-cover shadow-md ring-2 ring-card transition group-hover:rotate-0"
                style="left: {{ $loop->index * 8 }}px; z-index: {{ 3 - $loop->index }}; rotate: {{ ($loop->index - 1) * 6 }}deg"
            >
        @endforeach
    </span>
    <span class="min-w-0 flex-1 leading-tight">
        <span class="block truncate text-sm font-medium">{{ $count }} {{ Str::plural('game', $count) }} free to keep</span>
        <span class="block truncate text-xs text-muted">
            {{ collect($games)->take(2)->pluck('title')->join(', ') }}{{ $count > 2 ? ' and more' : '' }}
        </span>
    </span>
    <svg class="size-4 shrink-0 text-muted transition group-hover:translate-x-0.5 group-hover:text-ink" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
</a>

<dialog
    id="free-games"
    aria-labelledby="free-games-title"
    class="m-auto w-[calc(100%-2rem)] max-w-xl rounded-3xl border border-line bg-card p-0 text-ink shadow-2xl backdrop:bg-black/40 backdrop:backdrop-blur-sm"
>
    <div class="flex max-h-[85vh] flex-col">
        <div class="border-b border-line p-5 pb-4 sm:px-6">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="font-mono text-xs tracking-wider text-muted uppercase">Free to keep</p>
                    <h2 id="free-games-title" class="mt-1 font-display text-2xl leading-tight font-semibold tracking-tight">
                        {{ $count }} {{ Str::plural('game', $count) }} free right now
                    </h2>
                </div>
                <button type="button" data-dialog-close aria-label="Close" class="-m-1.5 grid size-8 shrink-0 place-items-center rounded-full text-muted transition hover:bg-paper hover:text-ink">
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6l-12 12M6 6l12 12"/></svg>
                </button>
            </div>

            @if ($devices->count() > 1)
                <div data-filters="free-games-list" role="group" aria-label="Show games for" class="mt-4 flex flex-wrap gap-1.5">
                    <button type="button" data-filter="" aria-pressed="true" class="rounded-full border border-line px-3 py-1 text-xs transition hover:border-accent aria-pressed:border-accent aria-pressed:bg-accent-soft">All</button>
                    @foreach ($devices as $device)
                        <button type="button" data-filter="{{ $device }}" aria-pressed="false" class="rounded-full border border-line px-3 py-1 text-xs transition hover:border-accent aria-pressed:border-accent aria-pressed:bg-accent-soft">{{ $device }}</button>
                    @endforeach
                </div>
            @endif
        </div>

        <ul id="free-games-list" class="panel-scrollbar flex-1 divide-y divide-line overflow-y-auto px-5 sm:px-6">
            @foreach ($games as $game)
                <li data-filter-tags="{{ implode('|', $game['devices']) }}" class="flex gap-4 py-4">
                    <img src="{{ $game['image'] }}" alt="" loading="lazy" class="aspect-[2/1] w-28 shrink-0 self-start rounded-lg bg-paper object-cover sm:w-36">
                    <div class="min-w-0 flex-1">
                        <h3 class="leading-snug font-medium">{{ $game['title'] }}</h3>
                        <p class="mt-0.5 text-xs text-muted">
                            {{ collect([$game['store'], ...$game['devices']])->filter()->unique()->join(' · ') }}
                        </p>
                        @if ($game['description'])
                            <p class="mt-1.5 line-clamp-2 text-sm text-muted">{{ $game['description'] }}</p>
                        @endif
                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs">
                            <a href="{{ $game['url'] }}" target="_blank" rel="noopener nofollow" class="rounded-full bg-ink px-3 py-1 font-medium text-paper transition hover:opacity-85">
                                Get it free ↗&#xFE0E;
                            </a>
                            @if ($game['worth'])
                                <span class="text-muted line-through">{{ $game['worth'] }}</span>
                            @endif
                            <span @class(['font-mono', 'font-medium text-accent' => \App\Cms\FreeGames::endingSoon($game), 'text-muted' => ! \App\Cms\FreeGames::endingSoon($game)])>
                                {{ \App\Cms\FreeGames::ending($game) }}
                            </span>
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <p class="border-t border-line px-5 py-3 text-xs text-muted sm:px-6">
            Listed by <a href="https://www.gamerpower.com/" target="_blank" rel="noopener" class="underline decoration-line underline-offset-2 hover:text-ink">GamerPower</a>.
            Some stores ask you to log in to claim a game.
        </p>
    </div>
</dialog>
