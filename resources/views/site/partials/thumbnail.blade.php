{{--
    A post thumbnail, or a placeholder in the template's accent color with
    the title's first letter. Expects $post, $class (size and shape) and
    $sizes (how wide it is shown, so the browser can pick a resized copy).
--}}
@if ($post->thumbnail)
    <img
        src="{{ $post->thumbnail->urlFor(800) }}"
        @if ($srcset = $post->thumbnail->srcset())
            srcset="{{ $srcset }}"
            sizes="{{ $sizes ?? '100vw' }}"
        @endif
        alt="{{ $post->thumbnail->alt ?? '' }}"
        loading="lazy"
        class="{{ $class }} bg-accent-soft object-cover"
    >
@else
    <div class="{{ $class }} thumb-placeholder grid place-items-center font-display font-semibold select-none" aria-hidden="true">
        <span class="{{ $letter ?? 'text-2xl' }} drop-shadow-sm">{{ Str::upper(Str::substr($post->title, 0, 1)) }}</span>
    </div>
@endif
