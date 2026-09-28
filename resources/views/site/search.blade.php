@extends('site.layout', ['title' => $query === '' ? 'Search' : "Search: {$query}"])

@section('content')
    @if ($posts === null)
        <h1 class="mb-2 text-2xl font-bold">Search</h1>
        <p class="text-neutral-500">Type something in the search box to find posts.</p>
    @else
        <h1 class="mb-6 text-2xl font-bold">
            {{ $posts->total() }} {{ Str::plural('result', $posts->total()) }} for “{{ $query }}”
        </h1>

        <ul class="divide-y divide-neutral-200 dark:divide-neutral-800">
            @forelse ($posts as $post)
                <li class="py-4">
                    <a href="{{ $post->url() }}" class="font-medium hover:underline">{{ $post->title }}</a>
                    <p class="mt-1 text-sm text-neutral-500">
                        <a href="{{ route('site.template', $post->template) }}" class="hover:underline">{{ $post->template->name }}</a>
                        &middot; {{ $post->published_at?->format('F j, Y') }}
                    </p>
                    @php($snippet = $excerpt($post))
                    @if ($snippet->toHtml() !== '')
                        <p class="mt-2 text-sm text-neutral-700 dark:text-neutral-300 [&_mark]:rounded-sm [&_mark]:bg-amber-200 [&_mark]:px-0.5 dark:[&_mark]:bg-amber-800 dark:[&_mark]:text-neutral-100">
                            {{ $snippet }}
                        </p>
                    @endif
                </li>
            @empty
                <li class="py-4 text-neutral-500">Nothing matched. Try different or fewer words.</li>
            @endforelse
        </ul>

        <div class="mt-6">
            {{ $posts->links() }}
        </div>
    @endif
@endsection
