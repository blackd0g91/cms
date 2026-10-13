@extends('site.auth.layout', ['title' => 'Check your inbox'])

@section('intro')
    We sent a link to <strong class="font-medium text-ink">{{ auth()->user()->email }}</strong>. Open it to confirm the address is yours.
@endsection

@section('form')
    <form method="post" action="{{ route('verification.send') }}">
        @csrf
        <p class="mb-4 text-sm text-muted">Nothing there? Look in your spam folder, or have it sent again.</p>
        @include('site.auth.button', ['label' => 'Send the link again'])
    </form>
@endsection

@section('after')
    <a href="{{ route('home') }}" class="font-medium text-ink underline underline-offset-4 hover:text-accent">Go to the home page</a>
@endsection
