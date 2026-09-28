@extends('site.layout', ['title' => $template->name])

@section('content')
    <div class="hue" style="--hue: {{ $template->hue() }}">
        <header class="relative mb-10 overflow-hidden rounded-3xl border border-line bg-card px-6 py-5">
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
                    {{ $posts->count() }} {{ Str::plural('entry', $posts->count()) }}
                </p>
            </div>
        </header>

        @include('site.partials.post-grid', ['posts' => $posts])
    </div>
@endsection
