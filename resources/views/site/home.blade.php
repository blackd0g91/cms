@extends('site.layout')

@section('content')
    <section class="mb-10">
        @if ($intro)
            <div class="prose prose-lg max-w-2xl">
                {{ $intro }}
            </div>
        @else
            <p class="font-mono text-xs tracking-widest text-muted uppercase">Fresh from the notebook</p>
            <h1 class="mt-2 font-display text-5xl font-semibold tracking-tight">
                Latest <em class="font-normal text-accent">entries</em>
            </h1>
        @endif
    </section>

    @include('site.partials.post-grid', ['posts' => $posts, 'featureFirst' => true])
@endsection
