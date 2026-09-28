@extends('site.layout', ['title' => $post->title])

@section('content')
    @unless ($post->isPublished())
        <p class="mb-6 rounded-md bg-amber-100 px-4 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200">
            This post is a draft. Only you can see it.
        </p>
    @endunless

    <header class="mb-8 flex items-center gap-5">
        @if ($post->thumbnail)
            @include('site.partials.thumbnail', ['post' => $post, 'size' => 'size-20 sm:size-28'])
        @endif
        <div class="min-w-0">
            <h1 class="text-3xl font-bold tracking-tight sm:text-4xl">{{ $post->title }}</h1>
            <p class="mt-2 text-sm text-neutral-500">
                <a href="{{ route('site.template', $post->template) }}" class="hover:underline">{{ $post->template->name }}</a>
                @if ($post->published_at)
                    &middot; {{ $post->published_at->format('F j, Y') }}
                @endif
            </p>
        </div>
    </header>

    <div class="prose prose-neutral max-w-none dark:prose-invert">
        {{ $content }}
    </div>
@endsection
