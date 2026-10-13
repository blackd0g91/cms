{{-- See App\Mail\TestEmail. Styles are inline, as many email apps ignore the rest. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Test email from {{ $siteName }}</title>
    </head>
    <body style="margin: 0; padding: 32px 16px; background: #f5efe3; color: #221d17; font: 16px/1.5 -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif">
        <div style="max-width: 32rem; margin: 0 auto; padding: 28px; border: 1px solid #e2d8c6; border-radius: 16px; background: #fffcf5">
            <p style="margin: 0; font: 600 24px/1.25 Georgia, serif">{{ $siteName }}</p>
            <p style="margin: 20px 0 0">This is a test email. If you are reading it, the site can send email.</p>
            <p style="margin: 20px 0 0; color: #7b6f5f; font-size: 13px">
                Sent {{ $sentAt }} through the {{ $mailer }} mailer, from {{ config('app.url') }}.
            </p>
        </div>
    </body>
</html>
