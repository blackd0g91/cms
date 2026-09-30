@php
    // A missing post in a template that exists: offer the template's other posts.
    $template = request()->route('template');
    $template = $template instanceof \App\Models\Template ? $template : null;

    // The last part of the address as words, since it is usually a slug.
    $guess = Str::of(rawurldecode(request()->path()))
        ->afterLast('/')
        ->beforeLast('.')
        ->replace(['-', '_'], ' ')
        ->squish()
        ->limit(100, '')
        ->toString();
@endphp

@extends('errors.layouts.site', [
    'title' => 'Page not found',
    'message' => 'There is nothing at this address. It may have moved, or the link has a typo.',
    'accent' => $template?->accentStyle(),
])

@section('actions')
    <form action="{{ route('search') }}" method="get" role="search" class="flex max-w-md gap-2">
        <label for="not-found-search" class="sr-only">Search the site</label>
        <input
            id="not-found-search"
            type="search"
            name="q"
            value="{{ $guess }}"
            placeholder="Search"
            class="min-w-0 flex-1 rounded-full border border-line bg-card px-4 py-2 text-sm placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent-soft focus:outline-none"
        >
        <button type="submit" class="shrink-0 rounded-full bg-ink px-5 py-2 text-sm font-medium text-paper transition hover:bg-accent">
            Search
        </button>
    </form>

    <p class="mt-6 flex flex-wrap gap-2">
        @if ($template)
            <a href="{{ route('site.template', $template) }}" class="inline-flex items-center gap-2 rounded-full border border-line bg-card px-4 py-2 transition hover:border-accent">
                <span class="size-2 rounded-full bg-accent"></span>
                All {{ $template->name }}
            </a>
        @endif
        <a href="{{ route('home') }}" class="inline-flex items-center gap-2 rounded-full border border-line bg-card px-4 py-2 transition hover:border-accent">
            <span aria-hidden="true">←</span> Home page
        </a>
    </p>
@endsection
