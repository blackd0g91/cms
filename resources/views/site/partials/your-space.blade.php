{{--
    The home page's panel for the features that come with an account: for
    guests, what an account gives them; for members, their reminders due
    soon, with a quick way to add one. More features stack below in time.
--}}
@php
    $user = auth()->user();
@endphp
<div class="space-y-4">
    @if (! $user)
        <section class="rounded-2xl border border-line bg-card p-4 shadow-sm">
            <h2 class="font-display text-xl font-semibold tracking-tight">Your space</h2>
            <p class="mt-1.5 text-sm text-muted">A free account keeps your reminders here, on any device, with more to come.</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <a href="{{ route('register') }}" class="rounded-full bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-accent">Create an account</a>
                <a href="{{ route('login') }}" class="rounded-full border border-line px-4 py-2 text-sm transition hover:border-accent">Log in</a>
            </div>
        </section>
    @elseif (! $user->hasVerifiedEmail())
        <section class="rounded-2xl border border-line bg-card p-4 shadow-sm">
            <h2 class="font-display text-xl font-semibold tracking-tight">Your space</h2>
            <p class="mt-1.5 text-sm text-muted">Confirm your email address to start adding reminders.</p>
            <a href="{{ route('verification.notice') }}" class="mt-4 inline-block rounded-full bg-ink px-4 py-2 text-sm font-medium text-paper transition hover:bg-accent">Confirm my email</a>
        </section>
    @else
        @php
            $reminders = app(\App\Cms\Reminders::class);
            $soon = $reminders->groups($user, until: $reminders->soon($user), limit: 6);
        @endphp
        <section aria-labelledby="your-reminders" class="@container rounded-2xl border border-line bg-card p-4 shadow-sm">
            <div class="flex items-baseline justify-between gap-3">
                <h2 id="your-reminders" class="font-display text-xl font-semibold tracking-tight">Reminders</h2>
                <a href="{{ route('reminders.index') }}" class="text-xs text-muted underline-offset-4 hover:text-ink hover:underline">All</a>
            </div>

            <div class="mt-2">
                @if ($soon === [])
                    <p class="py-2 text-sm text-muted">Nothing due today or tomorrow.</p>
                @else
                    @include('site.reminders.groups', ['groups' => $soon, 'compact' => true])
                @endif
            </div>

            <div class="mt-3">
                @include('site.reminders.notify')
            </div>

            <div class="mt-4 border-t border-line pt-4">
                @include('site.reminders.form', ['compact' => true, 'idPrefix' => 'quick-reminder'])
                @if ($errors->any())
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $errors->first() }}</p>
                @endif
            </div>
        </section>
    @endif
</div>
