{{--
    Expects $posts with their template and thumbnail relations loaded.
    Thumbnails only take up space when at least one post in the list has one.
--}}
@php($withThumbnails = $posts->contains(fn ($post) => $post->thumbnail !== null))
<ul class="divide-y divide-neutral-200 dark:divide-neutral-800">
    @forelse ($posts as $post)
        <li class="flex gap-4 py-4">
            @if ($withThumbnails)
                <a href="{{ $post->url() }}" tabindex="-1" aria-hidden="true">
                    @include('site.partials.thumbnail', ['post' => $post, 'size' => 'size-16'])
                </a>
            @endif
            <div class="min-w-0">
                <a href="{{ $post->url() }}" class="font-medium hover:underline">{{ $post->title }}</a>
                <p class="mt-1 text-sm text-neutral-500">
                    @if ($showTemplate ?? false)
                        <a href="{{ route('site.template', $post->template) }}" class="hover:underline">{{ $post->template->name }}</a>
                        &middot;
                    @endif
                    {{ $post->published_at?->format('F j, Y') }}
                </p>
                @isset($excerpt)
                    @php($snippet = $excerpt($post))
                    @if ($snippet->toHtml() !== '')
                        <p class="mt-2 text-sm text-neutral-700 dark:text-neutral-300 [&_mark]:rounded-sm [&_mark]:bg-amber-200 [&_mark]:px-0.5 dark:[&_mark]:bg-amber-800 dark:[&_mark]:text-neutral-100">
                            {{ $snippet }}
                        </p>
                    @endif
                @endisset
            </div>
        </li>
    @empty
        <li class="py-4 text-neutral-500">{{ $empty ?? 'Nothing here yet.' }}</li>
    @endforelse
</ul>
