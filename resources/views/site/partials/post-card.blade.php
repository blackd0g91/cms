{{-- A post as a card. Expects $post (template and thumbnail loaded) and optionally $featured. --}}
@php($featured ??= false)
<article @class(['hue group', 'sm:col-span-2' => $featured]) style="{{ $post->template->accentStyle() }}">
    {{-- data-card-light: lit where the pointer is, and tilted toward it (see site.ts). --}}
    <a
        href="{{ $post->url() }}"
        data-card-light
        @class([
            'card-light flex h-full overflow-hidden rounded-2xl border border-line bg-card shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-xl hover:shadow-accent-soft',
            'flex-col sm:flex-row' => $featured,
            'flex-col' => ! $featured,
        ])
    >
        @include('site.partials.thumbnail', [
            'post' => $post,
            'class' => $featured ? 'aspect-[4/3] w-full sm:aspect-auto sm:w-1/2' : 'aspect-[4/3] w-full',
            'letter' => $featured ? 'text-8xl' : 'text-6xl',
            'sizes' => $featured ? '(min-width: 640px) 450px, 100vw' : '(min-width: 1024px) 300px, (min-width: 640px) 50vw, 100vw',
        ])
        <div @class(['flex flex-1 flex-col p-5', 'sm:justify-center sm:p-8' => $featured])>
            <p class="flex items-center gap-2 font-mono text-[11px] tracking-wider text-muted uppercase">
                <span class="size-2 rounded-full bg-accent"></span>
                {{ $post->template->name }}
                @if ($post->isPinned())
                    <span class="ml-auto rounded-full bg-accent-soft px-2 py-0.5 text-ink normal-case" title="Pinned">Pinned</span>
                @endif
            </p>
            <h2 @class([
                'mt-2 font-display leading-snug font-semibold tracking-tight decoration-accent decoration-2 underline-offset-4 group-hover:underline',
                'text-3xl sm:text-4xl' => $featured,
                'text-xl' => ! $featured,
            ])>
                {{ $post->title }}
            </h2>
            @if ($post->summary)
                <p @class(['mt-2 text-muted', 'sm:text-base' => $featured, 'text-sm' => ! $featured])>{{ $post->summary }}</p>
            @endif
            <p class="mt-auto pt-4 font-mono text-xs text-muted">
                @if ($post->published_at)
                    {{ $post->published_at->format('M j, Y') }} &middot;
                @endif
                {{ $post->readingMinutes() }} min read
            </p>
        </div>
    </a>
</article>
