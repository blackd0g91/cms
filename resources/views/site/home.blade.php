@extends('site.layout')

@section('content')
    {{--
        The page, with the panel for account features on the right on wide
        screens (where post pages have "On this page"), or first on others.
    --}}
    <div class="grid gap-8 xl:grid-cols-[minmax(0,1fr)_16rem] xl:gap-10">
        <div class="min-w-0">
            {{-- A row of small cards that open into dialogs. --}}
            <div class="mb-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-2">
                @include('site.partials.weather')
                @if ($picture)
                    @include('site.partials.picture-of-the-day', ['picture' => $picture])
                @endif
                @if ($games)
                    @include('site.partials.free-games', ['games' => $games])
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

            @include('site.partials.post-grid', ['posts' => $posts, 'featureFirst' => true, 'besidePanel' => true])
        </div>

        <aside aria-label="Your space" class="order-first xl:order-none">
            <div class="xl:sticky xl:top-10">
                @include('site.partials.your-space')
            </div>
        </aside>
    </div>
@endsection
