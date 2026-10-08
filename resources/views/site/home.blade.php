@extends('site.layout')

@section('content')
    @php
        // A wash of the templates' colors, one from each corner (see .home-hero).
        $corners = ['0% 0%', '100% 0%', '100% 100%', '0% 100%'];
        $wash = $templates->take(4)->values()
            ->map(fn ($template, $i) => "radial-gradient(at {$corners[$i]}, color-mix(in oklch, {$template->accentColor()} 26%, transparent), transparent 65%)")
            ->implode(', ');
    @endphp

    {{-- The greeting, the accent and the light follow the visitor's time of day (see site.ts). --}}
    <section class="hue daylight home-hero relative mb-12 overflow-hidden rounded-3xl border border-line px-6 py-8 shadow-sm sm:px-10 sm:py-10" @if ($wash !== '') style="--wash: {{ $wash }}" @endif data-daylight>
        <p class="mb-5 flex items-center gap-2 font-mono text-xs tracking-wider text-muted uppercase">
            <span class="size-2 rounded-full bg-accent"></span>
            <span data-greeting>Hello</span>
        </p>
        @if ($intro)
            <div class="prose prose-lg max-w-2xl">
                {{ $intro }}
            </div>
        @else
            <h1 class="font-display text-5xl font-semibold tracking-tight">
                Latest <em class="font-normal text-accent">entries</em>
            </h1>
        @endif

        @if ($templates->count() > 1)
            <nav aria-label="Templates" class="mt-8 flex flex-wrap gap-2">
                @foreach ($templates as $template)
                    <a
                        href="{{ route('site.template', $template) }}"
                        class="hue template-chip flex items-center gap-2 rounded-full border py-1.5 pr-2 pl-3 text-sm font-medium transition hover:-translate-y-0.5"
                        style="{{ $template->accentStyle() }}"
                    >
                        <span class="size-2 rounded-full bg-accent"></span>
                        {{ $template->name }}
                        <span class="post-card-label rounded-full px-1.5 py-0.5 font-mono text-[10px] leading-none">{{ $template->posts_count }}</span>
                    </a>
                @endforeach
            </nav>
        @endif
    </section>

    @if ($intro && $posts->isNotEmpty())
        <h2 class="mb-5 flex items-center gap-4 font-mono text-xs tracking-wider text-muted uppercase">
            Latest
            <span class="h-px flex-1 bg-line"></span>
        </h2>
    @endif

    @include('site.partials.post-grid', ['posts' => $posts, 'featureFirst' => true])
@endsection
