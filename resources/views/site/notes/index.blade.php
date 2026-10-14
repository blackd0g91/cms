@extends('site.layout', ['title' => 'Notes', 'meta' => ['noindex' => true]])

@section('content')
    <div class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="font-display text-4xl font-semibold tracking-tight">Notes</h1>
                <p class="mt-2 text-muted">Yours alone, in markdown. The latest show on <a href="{{ route('me') }}" class="text-ink underline underline-offset-4 hover:text-accent">your page</a> too.</p>
            </div>

            @if ($total > 0)
                <form method="get" action="{{ route('notes.index') }}" role="search" class="w-full sm:w-64">
                    @if ($label)
                        <input type="hidden" name="label" value="{{ $label->id }}">
                    @endif
                    <label for="notes-search" class="sr-only">Search your notes</label>
                    <input
                        id="notes-search"
                        type="search"
                        name="q"
                        value="{{ $search }}"
                        placeholder="Search your notes"
                        class="w-full rounded-full border border-line bg-card px-4 py-2 text-sm placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent-soft focus:outline-none"
                    >
                </form>
            @endif
        </div>

        @include('site.labels.filter', ['route' => 'notes.index'])

        <section aria-label="New note" class="rounded-3xl border border-line bg-card p-5 shadow-sm sm:p-6">
            @include('site.notes.form', ['wide' => true])
            @if ($errors->note->any() && ! old('editing'))
                <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $errors->note->first() }}</p>
            @endif
        </section>

        @if ($notes->isEmpty())
            <p class="rounded-3xl border border-dashed border-line p-10 text-center">
                @if ($label)
                    <span class="block font-display text-2xl italic">No notes labeled {{ $label->name }}.</span>
                    <a href="{{ route('notes.index') }}" class="mt-1 inline-block text-sm text-muted underline underline-offset-4 hover:text-ink">See all your notes</a>
                @elseif ($search !== '')
                    <span class="block font-display text-2xl italic">Nothing matches "{{ $search }}".</span>
                    <a href="{{ route('notes.index') }}" class="mt-1 inline-block text-sm text-muted underline underline-offset-4 hover:text-ink">See all your notes</a>
                @else
                    <span class="block font-display text-2xl italic">No notes yet.</span>
                    <span class="mt-1 block text-sm text-muted">Write the first one above: an idea, a list, a recipe to try.</span>
                @endif
            </p>
        @else
            <div class="grid items-start gap-5 lg:grid-cols-2">
                @foreach ($notes as $note)
                    @include('site.notes.note', ['note' => $note, 'user' => auth()->user()])
                @endforeach
            </div>

            @include('site.partials.pagination', ['paginator' => $notes])
        @endif
    </div>

    @include('site.labels.dialog')
@endsection
