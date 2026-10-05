{{--
    The posts of a posts field, rendered with {{ field }} in a layout (see
    App\Cms\LinkedPosts). Expects $posts, with template and thumbnail loaded.

    Cards inside a post: kept out of its text styles, and with titles that
    are not headings, so they stay out of its table of contents.
--}}
<div class="not-prose my-8 grid gap-5 sm:grid-cols-2">
    @foreach ($posts as $post)
        @include('site.partials.post-card', ['post' => $post, 'titleTag' => 'p'])
    @endforeach
</div>
