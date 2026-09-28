@extends('site.layout')

@section('content')
    <h1 class="mb-6 text-3xl font-bold">Latest posts</h1>

    @include('site.partials.post-list', ['posts' => $posts, 'showTemplate' => true])
@endsection
