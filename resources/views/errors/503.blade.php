{{--
    Maintenance mode. deploy.sh pre-renders this page when it takes the site
    down, so there is no $exception then.
--}}
@extends('errors.layouts.standalone', [
    'code' => 503,
    'title' => 'Back soon',
    'message' => 'The site is being updated and will be back shortly.',
])

@section('actions')
    {{-- An empty link reloads whichever page the visitor was on. --}}
    <a href="" class="button">Try again</a>
@endsection
