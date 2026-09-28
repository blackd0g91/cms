{{--
    Description, canonical URL and link preview tags (Open Graph, Twitter).
    Pages pass $meta with any of: description, image, type, published_time,
    modified_time, noindex. Everything else falls back to the site settings.
--}}
@php
    $meta ??= [];
    $pageTitle = isset($title) ? $title.' - '.$siteName : $siteName;
    $description = Str::limit(trim((string) ($meta['description'] ?? $settings->get('tagline') ?? '')), 200);
    $image = $meta['image'] ?? $settings->logo()?->url;
    $url = url()->current();
@endphp
@if ($description !== '')
    <meta name="description" content="{{ $description }}">
@endif
@if ($meta['noindex'] ?? false)
    <meta name="robots" content="noindex">
@else
    <link rel="canonical" href="{{ $url }}">
@endif

<meta property="og:site_name" content="{{ $siteName }}">
<meta property="og:title" content="{{ $title ?? $siteName }}">
<meta property="og:type" content="{{ $meta['type'] ?? 'website' }}">
<meta property="og:url" content="{{ $url }}">
@if ($description !== '')
    <meta property="og:description" content="{{ $description }}">
@endif
@if ($image)
    <meta property="og:image" content="{{ $image }}">
@endif
@isset($meta['published_time'])
    <meta property="article:published_time" content="{{ $meta['published_time'] }}">
@endisset
@isset($meta['modified_time'])
    <meta property="article:modified_time" content="{{ $meta['modified_time'] }}">
@endisset
<meta name="twitter:card" content="{{ isset($meta['image']) ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $pageTitle }}">

<link rel="alternate" type="application/rss+xml" title="{{ $siteName }}" href="{{ route('feed') }}">
@isset($meta['feed'])
    <link rel="alternate" type="application/rss+xml" title="{{ $meta['feed']['title'] }}" href="{{ $meta['feed']['url'] }}">
@endisset
