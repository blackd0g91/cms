{{--
    One entry per template, in its accent color, after two tiles for the
    home page and the member's own page when logged in. Always shown on a
    card (the sidebar, or the menu on phones): templates are highlighted in
    their own color, other entries in the paper color.
--}}
<nav class="flex flex-1 flex-col text-sm">
    {{-- For those logged in: the home page and their own page, icons only. --}}
    @auth
        @php
            $placeTiles = [
                ['href' => route('home'), 'label' => 'Home page', 'current' => request()->routeIs('home'), 'icon' => '<path d="M3.5 10.5 12 3.5l8.5 7"/><path d="M5.5 9v10.5a1 1 0 0 0 1 1H10v-6h4v6h3.5a1 1 0 0 0 1-1V9"/>'],
                ['href' => route('me'), 'label' => 'Your page', 'current' => request()->routeIs('me', 'notes.*', 'reminders.*'), 'icon' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>'],
            ];
        @endphp
        <ul class="mb-3 grid grid-cols-2 gap-2 border-b border-line pb-3">
            @foreach ($placeTiles as $tile)
                @php
                    $tileClass = $tile['current']
                        ? 'border-accent bg-paper text-ink ring-1 ring-accent'
                        : 'border-line bg-paper/60 text-muted hover:-translate-y-0.5 hover:border-accent hover:bg-paper hover:text-ink';
                @endphp
                <li>
                    <a
                        href="{{ $tile['href'] }}"
                        title="{{ $tile['label'] }}"
                        class="grid place-items-center rounded-xl border py-2.5 transition {{ $tileClass }}"
                        @if ($tile['current']) aria-current="page" @endif
                    >
                        <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $tile['icon'] !!}</svg>
                        <span class="sr-only">{{ $tile['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endauth

    <ul class="space-y-0.5">
        @foreach ($navTemplates as $navTemplate)
            <li class="hue" style="{{ $navTemplate->accentStyle() }}">
                <a
                    href="{{ route('site.template', $navTemplate) }}"
                    @class([
                        'flex items-center gap-3 rounded-lg px-3 py-2 transition hover:bg-(--tint)',
                        'bg-(--tint) font-medium ring-1 ring-(--tint-line)' => $currentTemplate?->is($navTemplate),
                    ])
                >
                    <span class="size-2.5 shrink-0 rounded-full bg-accent"></span>
                    <span class="min-w-0 flex-1 truncate">{{ $navTemplate->name }}</span>
                    <span class="font-mono text-xs text-muted">{{ $navTemplate->posts_count }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    {{-- The links set in the control panel: at the bottom of the sidebar, and
         right below the rest in the menu on phones. --}}
    @if ($navLinks->isNotEmpty())
        <div class="mt-auto pt-6">
            <div class="border-t border-line pt-4">
                @if ($settings->get('links_heading') !== '')
                    <p class="mb-1 px-3 font-mono text-xs tracking-wider text-muted uppercase">{{ $settings->get('links_heading') }}</p>
                @endif
                <ul class="space-y-0.5">
                    @foreach ($navLinks as $navLink)
                        @php
                            $isCurrent = url()->current() === url((string) $navLink->href());
                        @endphp
                        <li>
                            <a
                                href="{{ $navLink->href() }}"
                                data-click="link:{{ $navLink->id }}"
                                @class([
                                    'flex items-center gap-3 rounded-lg px-3 py-2 transition hover:bg-paper hover:text-ink',
                                    'bg-paper font-medium text-ink ring-1 ring-line' => $isCurrent,
                                    'text-muted' => ! $isCurrent,
                                ])
                                @if ($isCurrent) aria-current="page" @endif
                            >
                                {{-- As wide as the dots above (10px), so every label lines up. --}}
                                <span class="-mx-[3px] w-4 shrink-0 text-center" aria-hidden="true">{{ $navLink->emoji }}</span>
                                <span class="min-w-0 flex-1 truncate">{{ $navLink->text() }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</nav>
