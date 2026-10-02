@extends('site.layout', [
    'title' => $post->title,
    'meta' => [
        'description' => $post->summary(),
        'image' => $preview['url'],
        'image_width' => $preview['width'],
        'image_height' => $preview['height'],
        'type' => 'article',
        'published_time' => $post->published_at?->toIso8601String(),
        'modified_time' => $post->updated_at?->toIso8601String(),
        'author' => $post->author?->name,
        // Drafts are only visible when logged in, so keep them out of search engines.
        'noindex' => ! $post->isPublished(),
        'feed' => ['title' => $post->template->name, 'url' => route('site.template.feed', $post->template)],
    ],
])

@section('content')
    <div class="hue flex gap-12" style="{{ $post->template->accentStyle() }}">
    <article @class(['min-w-0 flex-1', 'max-w-3xl' => $headings === []])>
        @unless ($post->isPublished())
            <p class="mb-6 rounded-xl border border-dashed border-accent bg-accent-soft px-4 py-2 font-mono text-xs">
                Draft preview. Only people who can log in see this.
            </p>
        @endunless

        <a href="{{ route('site.template', $post->template) }}" class="inline-flex items-center gap-2 font-mono text-xs tracking-wider text-muted uppercase hover:text-ink">
            <span aria-hidden="true">←</span>
            <span class="size-2 rounded-full bg-accent"></span>
            {{ $post->template->name }}
        </a>

        <header class="mt-5 mb-10 flex items-center gap-6">
            @if ($post->thumbnail)
                @include('site.partials.thumbnail', [
                    'post' => $post,
                    'class' => 'size-24 shrink-0 -rotate-3 rounded-2xl shadow-lg ring-4 ring-card sm:size-32',
                    'sizes' => '128px',
                ])
            @endif
            <div class="min-w-0">
                <h1 class="font-display text-4xl leading-tight font-semibold tracking-tight sm:text-5xl">{{ $post->title }}</h1>
                <p class="mt-3 font-mono text-xs text-muted">
                    @if ($post->author)
                        By {{ $post->author->name }} &middot;
                    @endif
                    @if ($post->published_at)
                        {{ $post->published_at->format('F j, Y') }} &middot;
                    @endif
                    {{ $post->readingMinutes() }} min read
                </p>
                {{-- Shown by site.ts where the browser can keep the screen on. --}}
                <button
                    type="button"
                    data-wake-lock
                    aria-pressed="false"
                    hidden
                    class="mt-3 inline-flex items-center gap-2 rounded-full border border-line bg-card px-3 py-1 align-top font-mono text-xs text-muted transition hover:border-accent hover:text-ink aria-pressed:border-accent aria-pressed:bg-accent-soft aria-pressed:text-ink"
                >
                    <span class="size-2 rounded-full bg-line transition in-aria-pressed:bg-accent" aria-hidden="true"></span>
                    <span data-wake-lock-label>Keep screen on</span>
                </button>

                {{-- For moving to a phone, so not shown on phones. No script needed. --}}
                <details data-popover class="relative mt-3 ml-1 hidden align-top sm:inline-block">
                    <summary class="inline-flex cursor-pointer list-none items-center gap-2 rounded-full border border-line bg-card px-3 py-1 font-mono text-xs text-muted transition hover:border-accent hover:text-ink">
                        <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 5a2 2 0 0 1 2 -2h8a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-8a2 2 0 0 1 -2 -2v-14z"/><path d="M11 4h2"/><path d="M12 17v.01"/></svg>
                        Open on phone
                    </summary>
                    <div class="absolute top-full left-0 z-20 mt-2 w-56 rounded-2xl border border-line bg-card p-4 text-center shadow-xl">
                        <span class="widget widget-qr !m-0 !shadow-none">{!! \App\Cms\QrCode::svg($post->url()) !!}</span>
                        <p class="mt-2 text-xs text-muted">Point your phone's camera at it.</p>
                    </div>
                </details>
            </div>
        </header>

        @if ($headings !== [])
            <details class="mb-8 rounded-2xl border border-line bg-card px-5 py-3 xl:hidden">
                <summary class="cursor-pointer font-mono text-xs tracking-widest text-muted uppercase">On this page</summary>
                <div class="mt-3 [&_[data-toc]>p]:hidden">
                    @include('site.partials.toc', ['headings' => $headings])
                </div>
            </details>
        @endif

        <div class="prose max-w-none">
            {{ $content }}
        </div>

        @if ($post->tags->isNotEmpty())
            <ul class="mt-12 flex flex-wrap gap-2 border-t border-line pt-6">
                @foreach ($post->tags as $tag)
                    <li>
                        <a href="{{ route('site.tag', $tag) }}" class="inline-flex rounded-full border border-line bg-card px-3 py-1 font-mono text-xs text-muted transition hover:border-accent hover:text-ink">#{{ $tag->name }}</a>
                    </li>
                @endforeach
            </ul>
        @endif

        @if ($related->isNotEmpty())
            <section class="mt-16 border-t border-line pt-8" aria-labelledby="related-heading">
                <h2 id="related-heading" class="mb-5 font-display text-2xl font-semibold tracking-tight">Keep reading</h2>
                <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($related as $relatedPost)
                        @include('site.partials.post-card', ['post' => $relatedPost])
                    @endforeach
                </div>
            </section>
        @endif
    </article>

    @if ($headings !== [])
        <aside class="hidden w-52 shrink-0 xl:block">
            <div class="sticky top-10 max-h-[calc(100vh-5rem)] overflow-y-auto rounded-2xl border border-line bg-card p-4">
                @include('site.partials.toc', ['headings' => $headings])
            </div>
        </aside>
    @endif
    </div>
@endsection
