@extends('site.layout', ['title' => $post->title])

@section('content')
    <article class="hue max-w-3xl" style="--hue: {{ $post->template->hue() }}">
        @unless ($post->isPublished())
            <p class="mb-6 rounded-xl border border-dashed border-accent bg-accent-soft px-4 py-2 font-mono text-xs">
                Draft preview. Only you can see this.
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
                ])
            @endif
            <div class="min-w-0">
                <h1 class="font-display text-4xl leading-tight font-semibold tracking-tight sm:text-5xl">{{ $post->title }}</h1>
                <p class="mt-3 font-mono text-xs text-muted">
                    @if ($post->published_at)
                        {{ $post->published_at->format('F j, Y') }} &middot;
                    @endif
                    {{ $post->readingMinutes() }} min read
                </p>
            </div>
        </header>

        <div class="prose max-w-none">
            {{ $content }}
        </div>
    </article>
@endsection
