{{-- One entry per template, in its accent color. --}}
<nav class="text-sm">
    <ul class="space-y-0.5">
        @foreach ($navTemplates as $navTemplate)
            <li class="hue" style="{{ $navTemplate->accentStyle() }}">
                <a
                    href="{{ route('site.template', $navTemplate) }}"
                    @class([
                        'flex items-center gap-3 rounded-lg px-3 py-2 transition hover:bg-card',
                        'bg-card font-medium shadow-sm ring-1 ring-line' => $currentTemplate?->is($navTemplate),
                    ])
                >
                    <span class="size-2.5 shrink-0 rounded-full bg-accent"></span>
                    <span class="min-w-0 flex-1 truncate">{{ $navTemplate->name }}</span>
                    <span class="font-mono text-xs text-muted">{{ $navTemplate->posts_count }}</span>
                </a>
            </li>
        @endforeach
    </ul>
</nav>
