@extends('site.layout', ['title' => $template->name])

@section('content')
    <h1 class="mb-2 text-3xl font-bold">{{ $template->name }}</h1>

    @if ($template->description)
        <p class="mb-6 text-neutral-600 dark:text-neutral-400">{{ $template->description }}</p>
    @endif

    @include('site.partials.post-list', ['posts' => $posts])
@endsection
