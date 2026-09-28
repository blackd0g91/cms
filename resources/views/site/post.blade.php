@extends('site.layout', ['title' => $post->title])

@section('content')
    <p class="mb-4 text-sm text-neutral-500">
        <a href="{{ route('site.template', $post->template) }}" class="hover:underline">{{ $post->template->name }}</a>
    </p>

    @unless ($post->isPublished())
        <p class="mb-6 rounded-md bg-amber-100 px-4 py-2 text-sm text-amber-900 dark:bg-amber-950 dark:text-amber-200">
            This post is a draft. Only you can see it.
        </p>
    @endunless

    <div class="prose prose-neutral max-w-none dark:prose-invert">
        {{ $content }}
    </div>
@endsection
