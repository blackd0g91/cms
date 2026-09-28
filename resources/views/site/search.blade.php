@extends('site.layout', [
    'title' => $query === '' ? 'Search' : "Search: {$query}",
    'meta' => ['noindex' => true],
])

@section('content')
    <p class="font-mono text-xs tracking-widest text-muted uppercase">Search</p>

    @if ($posts === null)
        <h1 class="mt-2 font-display text-4xl font-semibold tracking-tight">Looking for something?</h1>
        <p class="mt-2 text-muted">Type in the search box above, or press <kbd class="rounded border border-line px-1.5 font-mono text-xs">/</kbd> anywhere.</p>
    @else
        <h1 class="mt-2 mb-8 font-display text-4xl font-semibold tracking-tight">
            {{ $posts->total() }} {{ Str::plural('result', $posts->total()) }} for <em class="font-normal text-accent">“{{ $query }}”</em>
        </h1>

        @include('site.partials.post-list', ['posts' => $posts, 'excerpt' => $excerpt])

        <div class="mt-8">
            {{ $posts->links() }}
        </div>
    @endif
@endsection
