{{--
    Wikimedia Commons' picture of the day (see app/Cms/PictureOfTheDay.php):
    a small card of the picture, opening a dialog with it larger, what it
    shows and who made it. Without the script the card links to the
    picture's page on Commons. Expects $picture.
--}}
<a
    href="{{ $picture['page'] }}"
    data-dialog-open="picture-of-the-day"
    aria-haspopup="dialog"
    class="group relative flex min-h-16 items-end overflow-hidden rounded-2xl border border-line bg-card shadow-sm transition hover:border-accent"
>
    <img
        src="{{ $picture['image'] }}"
        alt=""
        loading="lazy"
        class="absolute inset-0 size-full object-cover transition duration-500 group-hover:scale-105"
    >
    <span class="absolute inset-0 bg-linear-to-r from-black/70 via-black/35 to-transparent" aria-hidden="true"></span>
    <span class="relative min-w-0 flex-1 p-3 leading-tight text-white">
        <span class="block font-mono text-[10px] tracking-wider uppercase opacity-80">Picture of the day</span>
        <span class="block truncate text-sm font-medium">{{ $picture['caption'] }}</span>
    </span>
    <svg class="relative mr-3 mb-3.5 size-4 shrink-0 text-white/80 transition group-hover:translate-x-0.5 group-hover:text-white" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>
</a>

<dialog
    id="picture-of-the-day"
    aria-labelledby="picture-of-the-day-title"
    class="m-auto w-[calc(100%-2rem)] max-w-3xl overflow-hidden rounded-3xl border border-line bg-card p-0 text-ink shadow-2xl backdrop:bg-black/50 backdrop:backdrop-blur-sm"
>
    <div class="relative bg-black">
        <img
            src="{{ $picture['image'] }}"
            alt="{{ $picture['caption'] }}"
            @if ($picture['width'] && $picture['height']) width="{{ $picture['width'] }}" height="{{ $picture['height'] }}" @endif
            loading="lazy"
            class="mx-auto max-h-[65vh] w-auto object-contain"
        >
        <button type="button" data-dialog-close aria-label="Close" class="absolute top-3 right-3 grid size-9 place-items-center rounded-full bg-black/50 text-white backdrop-blur transition hover:bg-black/70">
            <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6l-12 12M6 6l12 12"/></svg>
        </button>
    </div>

    <div class="p-5 sm:p-6">
        <p class="font-mono text-xs tracking-wider text-muted uppercase">Picture of the day</p>
        <h2 id="picture-of-the-day-title" class="mt-1 font-display text-2xl leading-tight font-semibold tracking-tight">{{ $picture['caption'] }}</h2>

        @if ($picture['description'] && $picture['description'] !== $picture['caption'])
            <p class="mt-3 text-sm leading-relaxed">{{ $picture['description'] }}</p>
        @endif

        <div class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-line pt-4 text-xs text-muted">
            <p class="min-w-0 flex-1">
                @if ($picture['artist'])
                    By {{ $picture['artist'] }}
                @endif
                @if ($picture['license'])
                    &middot;
                    @if ($picture['license_url'])
                        <a href="{{ $picture['license_url'] }}" rel="license noopener" target="_blank" class="underline decoration-line underline-offset-2 hover:text-ink">{{ $picture['license'] }}</a>
                    @else
                        {{ $picture['license'] }}
                    @endif
                @endif
                &middot; Wikimedia Commons
            </p>
            <a href="{{ $picture['page'] }}" target="_blank" rel="noopener" class="rounded-full border border-line px-3 py-1.5 text-ink transition hover:border-accent">
                Full size and details ↗&#xFE0E;
            </a>
        </div>
    </div>
</dialog>
