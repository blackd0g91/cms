@extends('site.layout', ['title' => 'Your account', 'meta' => ['noindex' => true]])

@section('content')
    <div class="max-w-2xl space-y-6">
        <div>
            <h1 class="font-display text-4xl font-semibold tracking-tight">Your account</h1>
            <p class="mt-2 text-muted">
                What we keep about you, and how to change or delete it. See the
                <a href="{{ route('privacy') }}" class="text-ink underline underline-offset-4 hover:text-accent">privacy note</a>.
            </p>
        </div>

        <section aria-labelledby="account-profile" class="rounded-3xl border border-line bg-card p-6 shadow-sm sm:p-8">
            <h2 id="account-profile" class="font-display text-2xl font-semibold tracking-tight">Name and email</h2>

            @unless ($user->hasVerifiedEmail())
                <p class="mt-3 flex items-center gap-2 text-sm text-accent">
                    <span class="size-2 shrink-0 rounded-full bg-accent" aria-hidden="true"></span>
                    Your address is not confirmed yet. <a href="{{ route('verification.notice') }}" class="underline underline-offset-4">Confirm it</a>
                </p>
            @endunless

            <form method="post" action="{{ route('account.update') }}" class="mt-5 space-y-4">
                @csrf
                @method('put')
                @include('site.auth.field', ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'value' => old('name', $user->name), 'autocomplete' => 'name', 'bag' => 'profile'])
                @include('site.auth.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => old('email', $user->email), 'autocomplete' => 'email', 'bag' => 'profile', 'hint' => 'A new address has to be confirmed, from a link sent to it.'])
                <button type="submit" class="rounded-full bg-ink px-5 py-2.5 text-sm font-medium text-paper transition hover:bg-accent">Save</button>
            </form>
        </section>

        <section aria-labelledby="account-password" class="rounded-3xl border border-line bg-card p-6 shadow-sm sm:p-8">
            <h2 id="account-password" class="font-display text-2xl font-semibold tracking-tight">Password</h2>

            <form method="post" action="{{ route('account.password') }}" class="mt-5 space-y-4">
                @csrf
                @method('put')
                @include('site.auth.field', ['name' => 'current_password', 'label' => 'Current password', 'type' => 'password', 'autocomplete' => 'current-password', 'bag' => 'password'])
                @include('site.auth.field', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'autocomplete' => 'new-password', 'bag' => 'password', 'hint' => 'A long one you do not use anywhere else.'])
                @include('site.auth.field', ['name' => 'password_confirmation', 'label' => 'New password again', 'type' => 'password', 'autocomplete' => 'new-password', 'bag' => 'password'])
                <button type="submit" class="rounded-full bg-ink px-5 py-2.5 text-sm font-medium text-paper transition hover:bg-accent">Change password</button>
            </form>
        </section>

        <section aria-labelledby="account-widgets" class="rounded-3xl border border-line bg-card p-6 shadow-sm sm:p-8">
            <h2 id="account-widgets" class="font-display text-2xl font-semibold tracking-tight">Home page cards</h2>
            <p class="mt-3 text-muted">The small cards at the top of the home page: tick the ones you want, and move them into the order you like.</p>

            @php
                // The chosen ones first, in their order, then the rest.
                $chosen = $user->homeWidgets();
                $widgets = collect($chosen)->merge(\App\Enums\HomeWidget::cases())->unique()->values();
            @endphp

            <form method="post" action="{{ route('account.widgets') }}" class="mt-5">
                @csrf
                @method('put')

                <ol data-sortable class="space-y-2">
                    @foreach ($widgets as $widget)
                        <li data-sortable-item class="flex items-center gap-3 rounded-2xl border border-line bg-paper p-3">
                            <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-3">
                                <input type="checkbox" name="widgets[]" value="{{ $widget->value }}" @checked(in_array($widget, $chosen, true)) class="size-4 shrink-0 accent-(--accent)">
                                <span class="text-2xl leading-none" aria-hidden="true">{{ $widget->emoji() }}</span>
                                <span class="min-w-0 leading-tight">
                                    <span class="block font-medium">{{ $widget->label() }}</span>
                                    <span class="block text-sm text-muted">{{ $widget->description() }}</span>
                                </span>
                            </label>
                            {{-- Shown by the script (see sortable.ts), which moves the card. --}}
                            <span data-sortable-buttons hidden class="flex shrink-0 gap-1">
                                <button type="button" data-move="up" aria-label="Move {{ $widget->label() }} up" title="Move up" class="grid size-8 place-items-center rounded-full border border-line text-muted transition hover:border-accent hover:text-ink disabled:pointer-events-none disabled:opacity-30">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 15l6-6 6 6"/></svg>
                                </button>
                                <button type="button" data-move="down" aria-label="Move {{ $widget->label() }} down" title="Move down" class="grid size-8 place-items-center rounded-full border border-line text-muted transition hover:border-accent hover:text-ink disabled:pointer-events-none disabled:opacity-30">
                                    <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9l6 6 6-6"/></svg>
                                </button>
                            </span>
                        </li>
                    @endforeach
                </ol>

                @error('widgets.*', 'widgets')
                    <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="mt-5 flex flex-wrap items-center gap-2">
                    <button type="submit" class="rounded-full bg-ink px-5 py-2.5 text-sm font-medium text-paper transition hover:bg-accent">Save</button>
                    @if ($user->home_widgets !== null)
                        <button type="submit" name="reset" value="1" class="rounded-full border border-line px-5 py-2.5 text-sm font-medium transition hover:border-accent">Back to the usual</button>
                    @endif
                </div>
            </form>
        </section>

        <section aria-labelledby="account-location" class="rounded-3xl border border-line bg-card p-6 shadow-sm sm:p-8">
            <h2 id="account-location" class="font-display text-2xl font-semibold tracking-tight">Weather location</h2>

            @if ($user->location)
                <p class="mt-3 text-muted">
                    <strong class="font-medium text-ink">{{ $user->location['name'] ?? 'A place without a name' }}</strong>,
                    from the weather card on the home page, rounded to about a kilometer. Your other devices start from it.
                </p>
                <form method="post" action="{{ route('account.location.destroy') }}" class="mt-5">
                    @csrf
                    @method('delete')
                    <button type="submit" class="rounded-full border border-line px-5 py-2.5 text-sm font-medium transition hover:border-accent">Forget it</button>
                </form>
            @else
                <p class="mt-3 text-muted">
                    None kept. When you show your weather on the home page while logged in, the place is kept here,
                    rounded to about a kilometer, so your other devices can start from it.
                </p>
            @endif
        </section>

        <section aria-labelledby="account-delete" class="rounded-3xl border border-red-300/60 bg-card p-6 shadow-sm sm:p-8 dark:border-red-900/60">
            <h2 id="account-delete" class="font-display text-2xl font-semibold tracking-tight">Delete your account</h2>

            @if ($user->isStaff())
                <p class="mt-3 text-muted">
                    You are part of the site's staff, so your account is removed by an admin, from the users page of the
                    <a href="{{ route('cp.dashboard') }}" class="text-ink underline underline-offset-4 hover:text-accent">control panel</a>.
                </p>
            @else
                <p class="mt-3 text-muted">
                    Your account and everything in it are deleted for good, straight away. This can not be undone.
                </p>

                <form method="post" action="{{ route('account.destroy') }}" class="mt-5 space-y-4">
                    @csrf
                    @method('delete')
                    @include('site.auth.field', ['name' => 'password', 'id' => 'account-delete-password', 'label' => 'Your password, to be sure', 'type' => 'password', 'autocomplete' => 'current-password', 'bag' => 'deletion'])
                    <button type="submit" class="rounded-full bg-red-700 px-5 py-2.5 text-sm font-medium text-white transition hover:bg-red-800">Delete my account</button>
                </form>
            @endif
        </section>
    </div>
@endsection
