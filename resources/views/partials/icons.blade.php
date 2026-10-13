{{--
    Browser tab and bookmark icons: the one chosen in Settings, or the
    defaults. Phones' home screens get the logo as a square instead (see
    AppIcon), as push notifications and the app manifest do.
--}}
@php($favicon = app(\App\Cms\Settings::class)->favicon())
@if ($favicon)
    <link rel="icon" href="{{ $favicon->url }}" type="{{ $favicon->mime_type }}">
@else
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
@endif
<link rel="apple-touch-icon" href="{{ app(\App\Cms\AppIcon::class)->url(192) }}">
{{-- The site as an app, for phones' home screens (see ManifestController). --}}
<link rel="manifest" href="/manifest.webmanifest">
