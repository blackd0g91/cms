{{--
    One entry per template, in its accent color. Always shown on a card (the
    sidebar, or the menu on phones), so highlights use the paper color.
--}}
<nav class="flex flex-1 flex-col text-sm">
    <ul class="space-y-0.5">
        @foreach ($navTemplates as $navTemplate)
            <li class="hue" style="{{ $navTemplate->accentStyle() }}">
                <a
                    href="{{ route('site.template', $navTemplate) }}"
                    @class([
                        'flex items-center gap-3 rounded-lg px-3 py-2 transition hover:bg-paper',
                        'bg-paper font-medium ring-1 ring-line' => $currentTemplate?->is($navTemplate),
                    ])
                >
                    <span class="size-2.5 shrink-0 rounded-full bg-accent"></span>
                    <span class="min-w-0 flex-1 truncate">{{ $navTemplate->name }}</span>
                    <span class="font-mono text-xs text-muted">{{ $navTemplate->posts_count }}</span>
                </a>
            </li>
        @endforeach
    </ul>

    @if ($hasTags)
        <a
            href="{{ route('site.tags') }}"
            @class([
                'mt-4 flex items-center gap-3 rounded-lg px-3 py-2 transition hover:bg-paper hover:text-ink',
                'bg-paper font-medium text-ink ring-1 ring-line' => request()->routeIs('site.tags', 'site.tag'),
                'text-muted' => ! request()->routeIs('site.tags', 'site.tag'),
            ])
        >
            <span class="w-2.5 text-center font-mono" aria-hidden="true">#</span>
            Tags
        </a>
    @endif

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
                        @php($isCurrent = url()->current() === url((string) $navLink->href()))
                        <li>
                            <a
                                href="{{ $navLink->href() }}"
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
                                @if ($navLink->isExternal())
                                    <span class="font-mono text-xs" aria-hidden="true">↗</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</nav>
