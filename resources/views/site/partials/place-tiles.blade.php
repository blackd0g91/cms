{{--
    Tiles for the home page and the member's own page, icons only, for
    everyone (visitors are asked to log in on the way to theirs), and below
    them, logged in, for their notes and reminders. Round buttons beside the
    site's name in the header ($inHeader), or tiles at the top of the menu
    on phones. The one for the page being viewed is marked.
--}}
@php
    $inHeader ??= false;
    $placeTiles = [
        ['href' => route('home'), 'label' => 'Home page', 'current' => request()->routeIs('home'), 'icon' => '<path d="M3.5 10.5 12 3.5l8.5 7"/><path d="M5.5 9v10.5a1 1 0 0 0 1 1H10v-6h4v6h3.5a1 1 0 0 0 1-1V9"/>'],
        ['href' => route('me'), 'label' => 'Your page', 'current' => request()->routeIs('me'), 'icon' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>'],
    ];

    if (auth()->check()) {
        $placeTiles[] = ['href' => route('notes.index'), 'label' => 'Notes', 'current' => request()->routeIs('notes.*'), 'icon' => '<path d="M14 3.5H7.5a2 2 0 0 0-2 2v13a2 2 0 0 0 2 2h9a2 2 0 0 0 2-2V8z"/><path d="M14 3.5V8h4.5M9 12.5h6M9 16h4"/>'];
        $placeTiles[] = ['href' => route('reminders.index'), 'label' => 'Reminders', 'current' => request()->routeIs('reminders.*'), 'icon' => '<rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path d="M3.5 9.5h17M8 3v4M16 3v4"/><path d="m9 15 2 2 4-4"/>'];
    }
@endphp
<ul @class(['flex items-center gap-1.5' => $inHeader, 'grid grid-cols-2 gap-2' => ! $inHeader])>
    @foreach ($placeTiles as $tile)
        @php
            $tileClass = $tile['current']
                ? 'border-accent bg-card text-ink ring-1 ring-accent'
                : 'border-line bg-card text-muted hover:border-accent hover:text-ink';
            $shape = $inHeader ? 'size-10 rounded-full' : 'rounded-2xl py-3 shadow-sm';
        @endphp
        <li>
            <a
                href="{{ $tile['href'] }}"
                title="{{ $tile['label'] }}"
                class="grid place-items-center border transition {{ $shape }} {{ $tileClass }}"
                @if ($tile['current']) aria-current="page" @endif
            >
                <svg class="{{ $inHeader ? 'size-4.5' : 'size-5' }}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $tile['icon'] !!}</svg>
                <span class="sr-only">{{ $tile['label'] }}</span>
            </a>
        </li>
    @endforeach
</ul>
