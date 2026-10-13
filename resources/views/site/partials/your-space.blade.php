{{--
    The home page's panel for the features that come with an account: for
    guests, what an account gives them; for members, their reminders due in
    the week ahead, with a button to add one in a dialog. More features stack
    below in time.
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
            $soon = $reminders->groups($user, until: $reminders->weekAhead($user), limit: 8);
        @endphp
        <section aria-labelledby="your-reminders" class="rounded-2xl border border-line bg-card p-4 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h2 id="your-reminders" class="font-display text-xl font-semibold tracking-tight">
                    <a href="{{ route('reminders.index') }}" class="decoration-2 underline-offset-4 hover:underline">Reminders</a>
                </h2>
                {{-- Opens the form in a dialog; without the script, the reminders page. --}}
                <a
                    href="{{ route('reminders.index') }}"
                    data-dialog-open="new-reminder"
                    aria-haspopup="dialog"
                    title="Add a reminder"
                    class="grid size-8 shrink-0 place-items-center rounded-full border border-line text-muted transition hover:border-accent hover:text-ink"
                >
                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    <span class="sr-only">Add a reminder</span>
                </a>
            </div>

            <div class="mt-2">
                @if ($soon === [])
                    <p class="py-2 text-sm text-muted">Nothing due this week.</p>
                @else
                    @include('site.reminders.groups', ['groups' => $soon, 'compact' => true])
                @endif
            </div>

            <div class="mt-3">
                @include('site.reminders.notify')
            </div>
        </section>

        {{-- Opened again when the form comes back with an error. --}}
        <dialog
            id="new-reminder"
            aria-labelledby="new-reminder-title"
            @if ($errors->any()) data-dialog-open-now @endif
            class="m-auto w-[calc(100%-2rem)] max-w-md rounded-3xl border border-line bg-card p-0 text-ink shadow-2xl backdrop:bg-black/40 backdrop:backdrop-blur-sm"
        >
            <div class="p-5 sm:p-6">
                <div class="flex items-start justify-between gap-4">
                    <h2 id="new-reminder-title" class="font-display text-2xl font-semibold tracking-tight">New reminder</h2>
                    <button type="button" data-dialog-close aria-label="Close" class="-m-1.5 grid size-8 shrink-0 place-items-center rounded-full text-muted transition hover:bg-paper hover:text-ink">
                        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6l-12 12M6 6l12 12"/></svg>
                    </button>
                </div>

                @if ($errors->any())
                    <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $errors->first() }}</p>
                @endif

                <div class="mt-4">
                    @include('site.reminders.form', ['stacked' => true, 'idPrefix' => 'new-reminder'])
                </div>
            </div>
        </dialog>
    @endif
</div>
