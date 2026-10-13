@extends('site.auth.layout', ['title' => 'Choose a new password'])

@section('intro', 'You will be logged in once it is set.')

@section('form')
    <form method="post" action="{{ route('password.store') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        @include('site.auth.field', ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'value' => old('email', $email), 'autocomplete' => 'email'])
        @include('site.auth.field', ['name' => 'password', 'label' => 'New password', 'type' => 'password', 'autocomplete' => 'new-password', 'autofocus' => true, 'hint' => 'A long one you do not use anywhere else.'])
        @include('site.auth.field', ['name' => 'password_confirmation', 'label' => 'New password again', 'type' => 'password', 'autocomplete' => 'new-password'])
        @include('site.auth.button', ['label' => 'Set my password'])
    </form>
@endsection

@section('after')
    Link not working? <a href="{{ route('password.request') }}" class="font-medium text-ink underline underline-offset-4 hover:text-accent">Ask for a new one</a>
@endsection
