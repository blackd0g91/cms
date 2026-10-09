{{--
    An error page inside the site, with its header, navigation and search. For
    client errors (4xx), where the site itself still works. Server errors use
    errors/layouts/standalone.blade.php instead.

    Views pass $title and $message, and optionally $accent (a template's
    accentStyle()) and an "actions" section to replace the home page link.
--}}
@extends('site.layout', ['title' => $title, 'meta' => ['noindex' => true], 'backdrop' => 'ripples'])

@section('content')
    @php($accent ??= null)
    <div @class(['max-w-2xl py-4 sm:py-10', 'hue' => $accent]) @if ($accent) style="{{ $accent }}" @endif>
        {{-- Redrawn in the dots behind the page (see partials/dot-ripples.blade.php). --}}
        <p class="inline-block font-display text-9xl leading-none font-bold tracking-tight text-accent sm:text-[12rem]" aria-hidden="true" data-dot-code>
            {{ $exception->getStatusCode() }}
        </p>
        <h1 class="mt-8 font-display text-4xl font-semibold tracking-tight">{{ $title }}</h1>
        <p class="mt-3 text-lg text-muted">{{ $message }}</p>

        <div class="mt-8">
            @section('actions')
                <a href="{{ route('home') }}" class="inline-flex items-center gap-2 rounded-full border border-line bg-card px-4 py-2 transition hover:border-accent">
                    <span aria-hidden="true">←</span> Go to the home page
                </a>
            @show
        </div>
    </div>
@endsection
