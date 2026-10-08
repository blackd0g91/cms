@extends('site.layout')

@section('content')
    {{-- The greeting, the accent and the light follow the visitor's time of day (see site.ts). --}}
    @if ($intro)
        @php
            // A wash of the templates' colors, one from each corner (see .home-hero).
            $corners = ['0% 0%', '100% 0%', '100% 100%', '0% 100%'];
            $wash = $templates->take(4)->values()
                ->map(fn ($template, $i) => "radial-gradient(at {$corners[$i]}, color-mix(in oklch, {$template->accentColor()} 26%, transparent), transparent 65%)")
                ->implode(', ');
        @endphp

        <section class="hue daylight home-hero relative mb-12 overflow-hidden rounded-3xl border border-line px-6 py-8 shadow-sm sm:px-10 sm:py-10" @if ($wash !== '') style="--wash: {{ $wash }}" @endif data-daylight>
            <div class="mb-5">@include('site.partials.greeting')</div>
            <div class="prose prose-lg max-w-2xl">
                {{ $intro }}
            </div>
            @include('site.partials.template-chips', ['class' => 'mt-8'])
        </section>

        @if ($posts->isNotEmpty())
            <h2 class="mb-5 flex items-center gap-4 font-mono text-xs tracking-wider text-muted uppercase">
                Latest
                <span class="h-px flex-1 bg-line"></span>
            </h2>
        @endif
    @else
        {{-- Without an intro, the posts come first, with the greeting and templates above them. --}}
        <h1 class="sr-only">Latest entries</h1>
        <div class="hue daylight relative mb-6 flex flex-wrap items-center justify-between gap-x-6 gap-y-3" data-daylight>
            @include('site.partials.greeting')
            @include('site.partials.template-chips')
        </div>
    @endif

    @include('site.partials.post-grid', ['posts' => $posts, 'featureFirst' => true])
@endsection
