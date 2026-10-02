@extends('site.layout', [
    'title' => "#{$tag->name}".($posts->currentPage() > 1 ? " – page {$posts->currentPage()}" : ''),
    'meta' => ['description' => "Posts tagged {$tag->name}"],
])

@section('content')
    <header class="mb-10 flex items-end justify-between gap-6">
        <div>
            <a href="{{ route('site.tags') }}" class="font-mono text-xs tracking-wider text-muted uppercase hover:text-ink">← All tags</a>
            <h1 class="mt-2 font-display text-5xl font-semibold tracking-tight"><span class="text-muted">#</span>{{ $tag->name }}</h1>
        </div>
        <p class="shrink-0 font-mono text-xs text-muted">{{ $posts->total() }} {{ Str::plural('entry', $posts->total()) }}</p>
    </header>

    @include('site.partials.post-grid', ['posts' => $posts, 'empty' => 'Nothing published with this tag yet.'])
    @include('site.partials.pagination', ['paginator' => $posts])
@endsection
