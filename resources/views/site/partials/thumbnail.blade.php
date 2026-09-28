{{-- A square post thumbnail, or an empty placeholder. Expects $post and $size (Tailwind size class). --}}
@if ($post->thumbnail)
    <img
        src="{{ $post->thumbnail->url }}"
        alt="{{ $post->thumbnail->alt ?? '' }}"
        loading="lazy"
        class="{{ $size }} shrink-0 rounded-md bg-neutral-100 object-cover dark:bg-neutral-800"
    >
@else
    <div class="{{ $size }} shrink-0 rounded-md bg-neutral-100 dark:bg-neutral-800" aria-hidden="true"></div>
@endif
