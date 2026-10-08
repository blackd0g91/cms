{{--
    A post as a card in its template's color. Expects $post (template and
    thumbnail loaded), and optionally $featured, and $titleTag for the
    title's element (h2 unless the card is inside a post, where its title is
    not one of the post's headings).
--}}
@php($featured ??= false)
@php($titleTag ??= 'h2')
<article @class(['hue group', 'sm:col-span-2' => $featured]) style="{{ $post->template->accentStyle() }}">
    {{-- data-card-light: lit where the pointer is, and tilted toward it (see site.ts). --}}
    <a
        href="{{ $post->url() }}"
        data-card-light
        @class([
            'post-card card-light flex h-full gap-1 overflow-hidden rounded-3xl border p-2 shadow-sm transition duration-200 hover:-translate-y-1 hover:shadow-xl hover:shadow-accent-soft focus-visible:outline-3 focus-visible:outline-offset-3 focus-visible:outline-accent',
            'post-card-featured flex-col sm:flex-row' => $featured,
            'flex-col' => ! $featured,
        ])
    >
        {{-- The picture, with the template's name over it. --}}
        <div @class([
            'relative shrink-0',
            'aspect-[4/3] w-full sm:aspect-auto sm:w-1/2' => $featured,
            'aspect-[16/10] w-full sm:aspect-[4/3]' => ! $featured,
        ])>
            @include('site.partials.thumbnail', [
                'post' => $post,
                'class' => 'size-full rounded-2xl',
                'letter' => $featured ? 'text-9xl' : 'text-7xl',
                'sizes' => $featured ? '(min-width: 640px) 450px, 100vw' : '(min-width: 1024px) 300px, (min-width: 640px) 50vw, 100vw',
            ])
            <span class="post-card-label absolute top-3 right-3 rounded-full px-2.5 py-1 font-mono text-[10px] leading-none font-medium tracking-wider uppercase shadow-md">{{ $post->template->name }}</span>
        </div>
        <div @class(['flex flex-1 flex-col px-3 pt-4 pb-2', 'sm:justify-center sm:px-6 sm:py-6' => $featured])>
            @if ($post->isPinned())
                <p class="mb-2 flex items-center gap-1 font-mono text-[10px] tracking-wider text-muted uppercase" title="Pinned">
                    <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 4.5l-4 4-4 1.5-1.5 1.5 7 7 1.5-1.5 1.5-4 4-4M9 15l-4.5 4.5M14.5 4 20 9.5"/></svg>
                    Pinned
                </p>
            @endif
            <{{ $titleTag }} @class([
                'font-display leading-snug font-semibold tracking-tight text-balance decoration-2 underline-offset-4 group-hover:underline',
                'text-3xl sm:text-4xl' => $featured,
                'text-xl' => ! $featured,
            ])>
                {{ $post->title }}
            </{{ $titleTag }}>
            @if ($post->summary)
                <p @class(['mt-2 text-muted', 'sm:text-base' => $featured, 'text-sm' => ! $featured])>{{ $post->summary }}</p>
            @endif
            <p class="mt-auto flex items-center gap-2 pt-4 font-mono text-xs text-muted">
                @if ($post->published_at)
                    {{ $post->published_at->format('M j, Y') }} &middot;
                @endif
                {{ $post->readingMinutes() }} min read
                <span class="ml-auto text-base leading-none transition-transform duration-200 group-hover:translate-x-1" aria-hidden="true">&rarr;</span>
            </p>
        </div>
    </a>
</article>
