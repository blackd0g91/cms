@extends('site.layout', [
    'title' => $template->name.($posts->currentPage() > 1 ? " – page {$posts->currentPage()}" : ''),
    'meta' => [
        'description' => $template->description,
        'feed' => ['title' => $template->name, 'url' => route('site.template.feed', $template)],
    ],
])

@section('content')
    <div class="hue" style="{{ $template->accentStyle() }}">
        <header class="relative mb-10 overflow-hidden rounded-3xl border border-(--tint-line) bg-(--tint) px-6 py-5">
            <div class="absolute -top-16 -right-16 size-44 rounded-full bg-accent-soft" aria-hidden="true"></div>
            <div class="absolute -right-4 -bottom-20 size-32 rounded-full bg-accent-soft" aria-hidden="true"></div>

            <div class="relative flex items-center justify-between gap-6">
                <div class="min-w-0">
                    <h1 class="font-display text-4xl font-semibold tracking-tight">{{ $template->name }}</h1>
                    @if ($template->description)
                        <p class="mt-1 max-w-xl text-muted">{{ $template->description }}</p>
                    @endif
                </div>
                <p class="shrink-0 font-mono text-xs text-muted">
                    {{ $posts->total() }} {{ Str::plural('entry', $posts->total()) }}
                </p>
            </div>
        </header>

        @include('site.partials.post-grid', ['posts' => $posts])
        @include('site.partials.pagination', ['paginator' => $posts])
    </div>
@endsection
