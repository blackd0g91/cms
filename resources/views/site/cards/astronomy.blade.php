{{--
    NASA's astronomy picture of the day (see App\Cms\AstronomyPicture): a
    card of the picture, like Wikimedia's, opening a dialog with it larger
    and NASA's explanation. Some days it is a video: then the card is plain
    and the dialog links to it. Expects $astronomy.
--}}
@if ($astronomy['image'])
    <a
        href="{{ $astronomy['page'] }}"
        data-dialog-open="astronomy-picture"
        aria-haspopup="dialog"
        class="group relative flex min-h-16 items-end overflow-hidden rounded-2xl border border-line bg-card shadow-sm transition hover:border-accent"
    >
        <img src="{{ $astronomy['image'] }}" alt="" loading="lazy" class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-105">
        <span class="absolute inset-0 bg-linear-to-r from-black/70 via-black/35 to-transparent" aria-hidden="true"></span>
        <span class="relative min-w-0 flex-1 p-3 leading-tight text-white">
            <span class="block font-mono text-[10px] tracking-wider uppercase opacity-80">NASA picture of the day</span>
            <span class="block truncate text-sm font-medium">{{ $astronomy['title'] }}</span>
        </span>
        <svg class="relative mr-3 mb-3.5 size-4 shrink-0 text-white/80 transition group-hover:translate-x-0.5 group-hover:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
    </a>
@else
    <x-site.card dialog="astronomy-picture" :href="$astronomy['page']" emoji="🔭" label="NASA picture of the day">
        {{ $astronomy['title'] }}
    </x-site.card>
@endif

<x-site.dialog id="astronomy-picture" :title="$astronomy['title']" width="max-w-3xl">
    @if ($astronomy['image'])
        <img src="{{ $astronomy['image'] }}" alt="{{ $astronomy['title'] }}" loading="lazy" class="mx-auto max-h-[60vh] w-auto rounded-2xl bg-black object-contain">
    @elseif ($astronomy['video'])
        <a href="{{ $astronomy['video'] }}" target="_blank" rel="noopener" class="flex items-center justify-center gap-2 rounded-2xl border border-dashed border-line p-8 text-sm transition hover:border-accent">
            ▶&#xFE0E; Today it is a video: watch it
        </a>
    @endif

    @if ($astronomy['explanation'])
        <p class="mt-4 text-sm leading-relaxed">{{ $astronomy['explanation'] }}</p>
    @endif

    <div class="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-line pt-3 text-xs text-muted">
        <span>
            @if ($astronomy['credit'])
                © {{ $astronomy['credit'] }} ·
            @endif
            NASA's Astronomy Picture of the Day
        </span>
        <a href="{{ $astronomy['page'] }}" target="_blank" rel="noopener" class="rounded-full border border-line px-3 py-1.5 text-ink transition hover:border-accent">On NASA's site ↗&#xFE0E;</a>
    </div>
</x-site.dialog>
