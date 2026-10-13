@extends('site.auth.layout', ['title' => 'Create an account'])

@section('intro', 'An account keeps your things with you, on any device.')

@section('form')
    <form method="post" action="{{ route('register.store') }}" class="space-y-4">
        @csrf
        {{-- The browser's time zone, for reminders (see site.ts). --}}
        <input type="hidden" name="timezone" data-timezone>
        @include('site.auth.field', ['name' => 'name', 'label' => 'Name', 'type' => 'text', 'value' => old('name'), 'autocomplete' => 'name', 'autofocus' => true])
        @include('site.auth.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => old('email'), 'autocomplete' => 'email', 'hint' => 'We send a link to confirm it, and use it only for your account.'])
        @include('site.auth.field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'new-password', 'hint' => 'A long one you do not use anywhere else.'])
        @include('site.auth.field', ['name' => 'password_confirmation', 'label' => 'Password again', 'type' => 'password', 'autocomplete' => 'new-password'])

        {{-- Left empty by people, who never see it; bots fill it in (see RegisterController). --}}
        <div class="absolute -left-[9999px]" aria-hidden="true">
            <label for="account-website">Website</label>
            <input id="account-website" name="website" type="text" tabindex="-1" autocomplete="off">
        </div>

        @include('site.auth.button', ['label' => 'Create account'])
        <p class="text-center text-xs text-muted">What is kept, and how to delete it: <a href="{{ route('privacy') }}" class="underline underline-offset-4 hover:text-ink">privacy</a>.</p>
    </form>
@endsection

@section('after')
    Already have one? <a href="{{ route('login') }}" class="font-medium text-ink underline underline-offset-4 hover:text-accent">Log in</a>
@endsection
