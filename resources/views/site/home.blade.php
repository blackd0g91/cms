@extends('site.layout')

@section('content')
    {{-- A row of small cards that open into dialogs: two of three columns for now. --}}
    <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        @include('site.partials.weather')
        @if ($picture)
            @include('site.partials.picture-of-the-day', ['picture' => $picture])
        @endif
    </div>

    @if ($intro)
        @php
            // A wash of the templates' colors, one from each corner (see .home-hero).
            $corners = ['0% 0%', '100% 0%', '100% 100%', '0% 100%'];
            $wash = $templates->take(4)->values()
                ->map(fn ($template, $i) => "radial-gradient(at {$corners[$i]}, color-mix(in oklch, {$template->accentColor()} 26%, transparent), transparent 65%)")
                ->implode(', ');
        @endphp

        <section class="home-hero relative mb-12 overflow-hidden rounded-3xl border border-line px-6 py-8 shadow-sm sm:px-10 sm:py-10" @if ($wash !== '') style="--wash: {{ $wash }}" @endif>
            <div class="prose prose-lg max-w-2xl">
                {{ $intro }}
            </div>
        </section>

        @if ($posts->isNotEmpty())
            <h2 class="mb-5 flex items-center gap-4 font-mono text-xs tracking-wider text-muted uppercase">
                Latest
                <span class="h-px flex-1 bg-line"></span>
            </h2>
        @endif
    @else
        {{-- Without an intro, the page is only the posts. --}}
        <h1 class="sr-only">Latest entries</h1>
    @endif

    @include('site.partials.post-grid', ['posts' => $posts, 'featureFirst' => true])
@endsection
