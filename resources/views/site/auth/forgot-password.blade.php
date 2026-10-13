@extends('site.auth.layout', ['title' => 'Forgot your password?'])

@section('intro', 'Enter the address you signed up with, and we will email you a link to choose a new one.')

@section('form')
    <form method="post" action="{{ route('password.email') }}" class="space-y-4">
        @csrf
        @include('site.auth.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => old('email'), 'autocomplete' => 'email', 'autofocus' => true])
        @include('site.auth.button', ['label' => 'Email me a link'])
    </form>
@endsection

@section('after')
    Remembered it? <a href="{{ route('login') }}" class="font-medium text-ink underline underline-offset-4 hover:text-accent">Log in</a>
@endsection
