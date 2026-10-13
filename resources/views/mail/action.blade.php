{{--
    An email that asks for one thing, with a button for it: confirming an
    address, choosing a new password. Styles are inline, as many email apps
    ignore the rest. Expects $siteName, $heading, $lines (paragraphs before
    the button), $actionText, $actionUrl and $outro, and optionally $links
    (more links under the button, label => address).
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ $heading }}</title>
    </head>
    <body style="margin: 0; padding: 32px 16px; background: #f5efe3; color: #221d17; font: 16px/1.5 -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif">
        <div style="max-width: 32rem; margin: 0 auto; padding: 28px; border: 1px solid #e2d8c6; border-radius: 16px; background: #fffcf5">
            <p style="margin: 0; color: #7b6f5f; font: 600 14px/1.25 Georgia, serif">{{ $siteName }}</p>
            <h1 style="margin: 12px 0 0; font: 600 24px/1.25 Georgia, serif">{{ $heading }}</h1>
            @foreach ($lines as $line)
                <p style="margin: 16px 0 0">{{ $line }}</p>
            @endforeach
            <p style="margin: 24px 0 0">
                <a href="{{ $actionUrl }}" style="display: inline-block; padding: 10px 20px; border-radius: 999px; background: #221d17; color: #f5efe3; font-weight: 600; text-decoration: none">{{ $actionText }}</a>
            </p>
            @if (! empty($links))
                <p style="margin: 16px 0 0; font-size: 14px">
                    @foreach ($links as $label => $url)
                        <a href="{{ $url }}" style="color: #221d17">{{ $label }}</a>@unless ($loop->last) &nbsp;·&nbsp; @endunless
                    @endforeach
                </p>
            @endif
            <p style="margin: 24px 0 0; color: #7b6f5f; font-size: 13px">{{ $outro }}</p>
            <p style="margin: 16px 0 0; color: #7b6f5f; font-size: 13px; word-break: break-all">
                If the button does not work, copy this address into your browser: {{ $actionUrl }}
            </p>
        </div>
    </body>
</html>
