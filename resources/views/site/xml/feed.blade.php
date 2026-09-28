{!! '<'.'?xml version="1.0" encoding="UTF-8"?>' !!}
<rss version="2.0" xmlns:atom="http://www.w3.org/2005/Atom" xmlns:content="http://purl.org/rss/1.0/modules/content/">
    <channel>
        <title>{{ $title }}</title>
        <link>{{ $link }}</link>
        <description>{{ $description }}</description>
        <atom:link href="{{ $self }}" rel="self" type="application/rss+xml"/>
        <language>{{ str_replace('_', '-', app()->getLocale()) }}</language>
        @if ($posts->isNotEmpty())
            <lastBuildDate>{{ $posts->first()->published_at?->toRssString() }}</lastBuildDate>
        @endif
        @foreach ($posts as $post)
            <item>
                <title>{{ $post->title }}</title>
                <link>{{ $post->url() }}</link>
                <guid isPermaLink="true">{{ $post->url() }}</guid>
                <category>{{ $post->template->name }}</category>
                @if ($post->published_at)
                    <pubDate>{{ $post->published_at->toRssString() }}</pubDate>
                @endif
                <description>{{ $post->summary(300) }}</description>
                <content:encoded>{{ ($post->thumbnail ? '<p><img src="'.e($post->thumbnail->url).'" alt=""></p>' : '').$render($post) }}</content:encoded>
            </item>
        @endforeach
    </channel>
</rss>
