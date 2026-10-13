@extends('site.layout', ['title' => 'Reminders', 'meta' => ['noindex' => true]])

@section('content')
    <div class="max-w-2xl space-y-6">
        <div>
            <h1 class="font-display text-4xl font-semibold tracking-tight">Reminders</h1>
            <p class="mt-2 text-muted">What to remember, and when. The ones due soon show on the home page too.</p>
            <div class="mt-2">
                @include('site.reminders.notify')
            </div>
        </div>

        <section aria-label="Add a reminder" class="rounded-3xl border border-line bg-card p-5 shadow-sm sm:p-6">
            @include('site.reminders.form')
            @if ($errors->any())
                <ul class="mt-3 space-y-1 text-sm text-red-600 dark:text-red-400">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section aria-label="Your reminders" class="rounded-3xl border border-line bg-card p-5 shadow-sm sm:p-6">
            @if ($groups === [])
                <p class="py-6 text-center">
                    <span class="block font-display text-2xl italic">Nothing to remember.</span>
                    <span class="mt-1 block text-sm text-muted">Add one above, like "call the dentist", for tomorrow at 10:00.</span>
                </p>
            @else
                @include('site.reminders.groups')
            @endif
        </section>
    </div>
@endsection
