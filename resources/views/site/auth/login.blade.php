@extends('site.auth.layout', ['title' => 'Log in'])

@section('intro', 'Welcome back.')

@section('form')
    <form method="post" action="{{ route('login.store') }}" class="space-y-4">
        @csrf
        @include('site.auth.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => old('email'), 'autocomplete' => 'email', 'autofocus' => true])
        @include('site.auth.field', ['name' => 'password', 'label' => 'Password', 'type' => 'password', 'autocomplete' => 'current-password'])

        <div class="flex items-center justify-between gap-4 text-sm">
            <label class="flex items-center gap-2">
                <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-4 accent-(--accent)">
                Keep me logged in
            </label>
            <a href="{{ route('password.request') }}" class="text-muted underline-offset-4 hover:text-ink hover:underline">Forgot your password?</a>
        </div>

        @include('site.auth.button', ['label' => 'Log in'])
    </form>
@endsection

@section('after')
    New here? <a href="{{ route('register') }}" class="font-medium text-ink underline underline-offset-4 hover:text-accent">Create an account</a>
@endsection
