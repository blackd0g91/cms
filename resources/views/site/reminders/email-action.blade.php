{{--
    Confirming a link from a reminder's email (see ReminderEmailController).
    The form posts back to the same signed address.
--}}
@extends('site.auth.layout', ['title' => $label])

@section('intro')
    <strong class="font-medium text-ink">{{ $reminder->text }}</strong>, due {{ $when }}.
@endsection

@section('form')
    <form method="post" action="{{ request()->fullUrl() }}">
        @csrf
        @include('site.auth.button', ['label' => $label])
    </form>
@endsection
