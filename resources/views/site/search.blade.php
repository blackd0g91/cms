@extends('site.layout', ['title' => $query === '' ? 'Search' : "Search: {$query}"])

@section('content')
    @if ($posts === null)
        <h1 class="mb-2 text-2xl font-bold">Search</h1>
        <p class="text-neutral-500">Type something in the search box to find posts.</p>
    @else
        <h1 class="mb-6 text-2xl font-bold">
            {{ $posts->total() }} {{ Str::plural('result', $posts->total()) }} for “{{ $query }}”
        </h1>

        @include('site.partials.post-list', [
            'posts' => $posts,
            'showTemplate' => true,
            'excerpt' => $excerpt,
            'empty' => 'Nothing matched. Try different or fewer words.',
        ])

        <div class="mt-6">
            {{ $posts->links() }}
        </div>
    @endif
@endsection
