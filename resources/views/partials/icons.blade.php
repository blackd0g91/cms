{{-- Browser tab and bookmark icons: the one chosen in Settings, or the defaults. --}}
@php($favicon = app(\App\Cms\Settings::class)->favicon())
@if ($favicon)
    <link rel="icon" href="{{ $favicon->url }}" type="{{ $favicon->mime_type }}">
    {{-- iOS home screen icons can not be SVG, so keep the default one for those. --}}
    <link rel="apple-touch-icon" href="{{ $favicon->isSvg() ? '/apple-touch-icon.png' : $favicon->url }}">
@else
    <link rel="icon" href="/favicon.ico" sizes="any">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png">
@endif
