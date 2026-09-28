{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ route('home') }}</loc>
        @if ($posts->isNotEmpty())
            <lastmod>{{ $posts->first()->updated_at?->toAtomString() }}</lastmod>
        @endif
    </url>
    @foreach ($templates as $template)
        <url>
            <loc>{{ route('site.template', $template) }}</loc>
        </url>
    @endforeach
    @foreach ($posts as $post)
        <url>
            <loc>{{ $post->url() }}</loc>
            <lastmod>{{ $post->updated_at?->toAtomString() }}</lastmod>
        </url>
    @endforeach
</urlset>
