@extends('site.layout', ['title' => 'Tags'])

@section('content')
    <h1 class="mb-8 font-display text-5xl font-semibold tracking-tight">Tags</h1>

    @if ($tags->isEmpty())
        <p class="text-muted">No tags yet.</p>
    @else
        <ul class="flex flex-wrap gap-2">
            @foreach ($tags as $tag)
                <li>
                    <a href="{{ route('site.tag', $tag) }}" class="inline-flex items-center gap-2 rounded-full border border-line bg-card px-4 py-2 transition hover:border-ink">
                        <span class="text-muted">#</span>{{ $tag->name }}
                        <span class="font-mono text-xs text-muted">{{ $tag->posts_count }}</span>
                    </a>
                </li>
            @endforeach
        </ul>
    @endif
@endsection
