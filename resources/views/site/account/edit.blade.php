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
