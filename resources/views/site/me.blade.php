@extends('site.layout', ['title' => 'Your page', 'meta' => ['noindex' => true]])

@section('content')
    {{--
        A member's own page (see MeController), made to stay open in a tab:
        their cards, then their reminders. More of their things join below
        in time.
    --}}
    <div class="space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
            <div>
                <p class="font-mono text-xs tracking-wider text-muted uppercase">{{ $today }}</p>
                <h1 class="mt-1 font-display text-4xl font-semibold tracking-tight">{{ $greeting }}, {{ $firstName }}</h1>
            </div>
            <a href="{{ route('account.edit') }}#account-widgets" class="text-sm text-muted underline-offset-4 hover:text-ink hover:underline">Choose your cards</a>
        </div>

        @include('site.partials.cards')

        @include('site.reminders.panel')
    </div>
@endsection
