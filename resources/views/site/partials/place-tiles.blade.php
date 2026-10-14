{{--
    Tiles for the home page and the member's own page, icons only, for
    everyone (visitors are asked to log in on the way to theirs). Floating
    above the sidebar on wide screens, each on a card of its own, and at the
    top of the menu on phones. The one for the page being viewed is marked.
--}}
@php
    $placeTiles = [
        ['href' => route('home'), 'label' => 'Home page', 'current' => request()->routeIs('home'), 'icon' => '<path d="M3.5 10.5 12 3.5l8.5 7"/><path d="M5.5 9v10.5a1 1 0 0 0 1 1H10v-6h4v6h3.5a1 1 0 0 0 1-1V9"/>'],
        ['href' => route('me'), 'label' => 'Your page', 'current' => request()->routeIs('me', 'notes.*', 'reminders.*'), 'icon' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>'],
    ];
@endphp
<ul class="grid shrink-0 grid-cols-2 gap-2">
    @foreach ($placeTiles as $tile)
        @php
            $tileClass = $tile['current']
                ? 'border-accent bg-card text-ink ring-1 ring-accent'
                : 'border-line bg-card text-muted hover:-translate-y-0.5 hover:border-accent hover:text-ink';
        @endphp
        <li>
            <a
                href="{{ $tile['href'] }}"
                title="{{ $tile['label'] }}"
                class="grid place-items-center rounded-2xl border py-3 shadow-sm transition {{ $tileClass }}"
                @if ($tile['current']) aria-current="page" @endif
            >
                <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $tile['icon'] !!}</svg>
                <span class="sr-only">{{ $tile['label'] }}</span>
            </a>
        </li>
    @endforeach
</ul>
