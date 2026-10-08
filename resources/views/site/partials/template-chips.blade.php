{{-- The templates to browse by, as pills in their colors. Expects $templates with posts_count, and $class. --}}
@if ($templates->count() > 1)
    <nav aria-label="Templates" class="{{ $class ?? '' }} flex flex-wrap gap-2">
        @foreach ($templates as $template)
            <a
                href="{{ route('site.template', $template) }}"
                class="hue template-chip flex items-center gap-2 rounded-full border py-1.5 pr-2 pl-3 text-sm font-medium transition hover:-translate-y-0.5"
                style="{{ $template->accentStyle() }}"
            >
                <span class="size-2 rounded-full bg-accent"></span>
                {{ $template->name }}
                <span class="post-card-label rounded-full px-1.5 py-0.5 font-mono text-[10px] leading-none">{{ $template->posts_count }}</span>
            </a>
        @endforeach
    </nav>
@endif
