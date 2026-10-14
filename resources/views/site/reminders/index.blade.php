@extends('site.layout', ['title' => 'Reminders', 'meta' => ['noindex' => true]])

@section('content')
    <div class="max-w-2xl space-y-6">
        <div>
            <h1 class="font-display text-4xl font-semibold tracking-tight">Reminders</h1>
            <p class="mt-2 text-muted">What to remember, and when. The ones due soon show on <a href="{{ route('me') }}" class="text-ink underline underline-offset-4 hover:text-accent">your page</a> too.</p>
            <div class="mt-2">
                @include('site.reminders.notify')
            </div>
        </div>

        @include('site.labels.filter', ['route' => 'reminders.index'])

        <section aria-label="Add a reminder" class="rounded-3xl border border-line bg-card p-5 shadow-sm sm:p-6">
            @include('site.reminders.form')
            @if ($errors->any() && ! old('editing'))
                <ul class="mt-3 space-y-1 text-sm text-red-600 dark:text-red-400">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section aria-label="Your reminders" class="rounded-3xl border border-line bg-card p-5 shadow-sm sm:p-6">
            @if ($groups === [] && $label)
                <p class="py-6 text-center">
                    <span class="block font-display text-2xl italic">No reminders labeled {{ $label->name }}.</span>
                    <a href="{{ route('reminders.index') }}" class="mt-1 inline-block text-sm text-muted underline underline-offset-4 hover:text-ink">See all your reminders</a>
                </p>
            @elseif ($groups === [])
                <p class="py-6 text-center">
                    <span class="block font-display text-2xl italic">Nothing to remember.</span>
                    <span class="mt-1 block text-sm text-muted">Add one above, like "call the dentist", for tomorrow at 10:00.</span>
                </p>
            @else
                @include('site.reminders.groups')
            @endif
        </section>
    </div>

    @include('site.labels.dialog')
@endsection
