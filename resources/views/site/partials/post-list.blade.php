{{-- Expects $posts with their template relation loaded. --}}
<ul class="divide-y divide-neutral-200 dark:divide-neutral-800">
    @forelse ($posts as $post)
        <li class="py-4">
            <a href="{{ $post->url() }}" class="font-medium hover:underline">{{ $post->title }}</a>
            <p class="mt-1 text-sm text-neutral-500">
                @if ($showTemplate ?? false)
                    <a href="{{ route('site.template', $post->template) }}" class="hover:underline">{{ $post->template->name }}</a>
                    &middot;
                @endif
                {{ $post->published_at?->format('F j, Y') }}
            </p>
        </li>
    @empty
        <li class="py-4 text-neutral-500">Nothing here yet.</li>
    @endforelse
</ul>
