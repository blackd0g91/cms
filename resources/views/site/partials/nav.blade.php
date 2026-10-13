{{--
    One entry per template, in its accent color. Always shown on a card (the
    sidebar, or the menu on phones): templates are highlighted in their own
    color, other entries in the paper color.
--}}
<nav class="flex flex-1 flex-col text-sm">
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

    {{-- The links set in the control panel, as small square tiles two to a
         row with the emoji (or the first letter) above the name: at the
         bottom of the sidebar, and right below the rest in the menu on
         phones. --}}
    @if ($navLinks->isNotEmpty())
        <div class="mt-auto pt-6">
            <div class="border-t border-line pt-4">
                @if ($settings->get('links_heading') !== '')
                    <p class="mb-2 px-3 font-mono text-xs tracking-wider text-muted uppercase">{{ $settings->get('links_heading') }}</p>
                @endif
                <ul class="grid grid-cols-2 gap-2">
                    @foreach ($navLinks as $navLink)
                        @php
                            $isCurrent = url()->current() === url((string) $navLink->href());
                            $tileClass = $isCurrent
                                ? 'border-accent bg-paper font-medium text-ink ring-1 ring-accent'
                                : 'border-line bg-paper/60 text-muted hover:-translate-y-0.5 hover:border-accent hover:bg-paper hover:text-ink';
                        @endphp
                        <li>
                            <a
                                href="{{ $navLink->href() }}"
                                data-click="link:{{ $navLink->id }}"
                                class="relative flex aspect-square flex-col items-center justify-center gap-1.5 rounded-xl border p-2 text-center transition {{ $tileClass }}"
                                @if ($isCurrent) aria-current="page" @endif
                            >
                                @if ($navLink->emoji)
                                    <span class="text-2xl leading-none" aria-hidden="true">{{ $navLink->emoji }}</span>
                                @else
                                    <span class="font-display text-2xl leading-none font-semibold" aria-hidden="true">{{ mb_strtoupper(mb_substr($navLink->text(), 0, 1)) }}</span>
                                @endif
                                <span class="line-clamp-2 w-full text-xs leading-tight break-words">{{ $navLink->text() }}</span>
                                @if ($navLink->isExternal())
                                    <span class="absolute top-1.5 right-2 font-mono text-[10px] text-muted" aria-hidden="true">↗</span>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    @endif
</nav>
