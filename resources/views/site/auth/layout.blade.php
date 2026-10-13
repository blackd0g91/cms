{{--
    The account pages (log in, sign up, passwords, confirming an address):
    a card with a heading, a line under it, and the form. Pages set $title,
    the "intro" and "form" sections, and optionally "after", below the card.
--}}
@extends('site.layout', ['title' => $title, 'meta' => ['noindex' => true]])

@section('content')
    <div class="mx-auto max-w-md py-2 sm:py-8">
        <div class="rounded-3xl border border-line bg-card p-6 shadow-sm sm:p-8">
            <h1 class="font-display text-3xl font-semibold tracking-tight">{{ $title }}</h1>
            <p class="mt-2 text-muted">@yield('intro')</p>

            <div class="mt-6">
                @yield('form')
            </div>
        </div>

        @hasSection('after')
            <p class="mt-5 text-center text-sm text-muted">@yield('after')</p>
        @endif
    </div>
@endsection
