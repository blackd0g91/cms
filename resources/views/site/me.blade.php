@extends('site.layout', ['title' => 'Your page', 'meta' => ['noindex' => true]])

@section('content')
    {{--
        A member's own page (see MeController), made to stay open in a tab:
        their cards, their quick links, then their reminders and notes side
        by side.
    --}}
    <div class="space-y-8">
        <div class="flex flex-wrap items-end justify-between gap-x-6 gap-y-2">
            <div>
                <p class="font-mono text-xs tracking-wider text-muted uppercase">{{ $today }}</p>
                <h1 class="mt-1 font-display text-4xl font-semibold tracking-tight">{{ $greeting }}, {{ $firstName }}</h1>
            </div>
            {{-- Opens the choice in a dialog; without the script, the account page. --}}
            <a
                href="{{ route('account.edit') }}#account-widgets"
                data-dialog-open="choose-cards"
                aria-haspopup="dialog"
                class="inline-flex items-center gap-2 rounded-full border border-line bg-card px-4 py-2 text-sm text-muted transition hover:border-accent hover:text-ink"
            >
                <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><path d="M17 14v6M14 17h6"/></svg>
                Choose your cards
            </a>
        </div>

        @include('site.partials.cards')

        @if ($widgets === [])
            <p class="rounded-2xl border border-dashed border-line p-4 text-center text-sm text-muted">
                No cards on your page. <a href="{{ route('account.edit') }}#account-widgets" data-dialog-open="choose-cards" class="text-ink underline underline-offset-4 hover:text-accent">Choose some</a>
            </p>
        @endif

        {{-- Opened again when the choice comes back with an error. --}}
        <dialog
            id="choose-cards"
            aria-labelledby="choose-cards-title"
            @if ($errors->widgets->any()) data-dialog-open-now @endif
            class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl border border-line bg-card p-0 text-ink shadow-2xl backdrop:bg-black/40 backdrop:backdrop-blur-sm"
        >
            <div class="p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <h2 id="choose-cards-title" class="font-display text-2xl font-semibold tracking-tight">Your cards</h2>
                    <button type="button" data-dialog-close aria-label="Close" class="-m-1.5 grid size-8 shrink-0 place-items-center rounded-full text-muted transition hover:bg-paper hover:text-ink">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6l-12 12M6 6l12 12"/></svg>
                    </button>
                </div>
                <p class="mt-2 text-sm text-muted">Tick the ones you want, and move them into the order you like.</p>
                <div class="mt-4">
                    @include('site.account.widgets-form')
                </div>
            </div>
        </dialog>

        @include('site.quick-links.panel')

        <div class="grid items-start gap-6 lg:grid-cols-2">
            <div>
                @include('site.reminders.panel')
            </div>
            @include('site.notes.panel')
        </div>
    </div>
@endsection
