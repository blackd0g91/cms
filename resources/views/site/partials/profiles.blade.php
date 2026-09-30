{{--
    The profiles filled in on the Settings page, as icons. Expects $profiles
    (from Settings::profiles()).
--}}
<nav aria-label="Profiles" class="flex flex-wrap items-center gap-0.5">
    @foreach ($profiles as $profile)
        <a
            href="{{ $profile['href'] }}"
            rel="me"
            title="{{ $profile['site']->label() }}"
            class="grid size-9 place-items-center rounded-full text-muted transition hover:bg-card hover:text-ink"
        >
            <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $profile['site']->icon() !!}</svg>
            <span class="sr-only">{{ $profile['site']->label() }}</span>
        </a>
    @endforeach
</nav>
