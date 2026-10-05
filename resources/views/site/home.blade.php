@extends('site.layout')

@section('content')
    {{-- The greeting, the accent and the light follow the visitor's time of day (see site.ts). --}}
    <section class="hue daylight relative mb-10" data-daylight>
        <p class="mb-4 flex items-center gap-2 font-mono text-xs tracking-wider text-muted uppercase">
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
    </section>

    @include('site.partials.post-grid', ['posts' => $posts, 'featureFirst' => true])
@endsection
