@extends('site.layout')

@section('content')
    @if ($intro)
        <div class="prose prose-neutral mb-10 max-w-none dark:prose-invert">
            {{ $intro }}
        </div>
    @endif

    <h1 class="mb-6 text-2xl font-bold">Latest posts</h1>

    @include('site.partials.post-list', ['posts' => $posts, 'showTemplate' => true])
@endsection
