{{--
    A self-contained error page, for when the site itself may be broken: server
    errors, and maintenance mode while deploy.sh replaces the code (it pre-renders
    errors::503, which is then served without booting the app). So nothing here
    may need the database or the built assets, and the styles are inline.

    Views pass $title and $message, $code when there is no $exception, and
    optionally an "actions" section to replace the home page link.
--}}
@php
    $code ??= $exception->getStatusCode();

    // The database may be what failed, so anything read from it is optional.
    $siteName = rescue(fn () => app(\App\Cms\Settings::class)->siteName(), config('app.name'), report: false);
    $icons = rescue(fn () => view('partials.icons')->render(), '<link rel="icon" href="/favicon.ico" sizes="any">', report: false);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="robots" content="noindex">
        <script>
            try {
                const theme = localStorage.getItem('site.theme');
                if (theme === 'light' || theme === 'dark') document.documentElement.dataset.theme = theme;
            } catch {}
        </script>
        <title>{{ $title }} - {{ $siteName }}</title>
        {!! $icons !!}

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link rel="stylesheet" href="https://fonts.bunny.net/css?family=fraunces:600|instrument-sans:400,500&display=swap">
        <style>
            /* The site's colors and type, from resources/css/site.css. */
            :root {
                color-scheme: light dark;
                --paper: #f5efe3;
                --card: #fffcf5;
                --ink: #221d17;
                --muted: #7b6f5f;
                --line: #e2d8c6;
                --accent: oklch(0.58 0.13 45);
                --display: Fraunces, ui-serif, Georgia, serif;
            }

            :root[data-theme='light'] {
                color-scheme: light;
            }

            :root[data-theme='dark'] {
                color-scheme: dark;
                --paper: #16130f;
                --card: #1e1a15;
                --ink: #efe7d8;
                --muted: #9c907d;
                --line: #332d25;
                --accent: oklch(0.76 0.11 45);
            }

            @media (prefers-color-scheme: dark) {
                :root:not([data-theme='light']) {
                    --paper: #16130f;
                    --card: #1e1a15;
                    --ink: #efe7d8;
                    --muted: #9c907d;
                    --line: #332d25;
                    --accent: oklch(0.76 0.11 45);
                }
            }

            * {
                box-sizing: border-box;
            }

            body {
                display: flex;
                flex-direction: column;
                min-height: 100vh;
                margin: 0;
                background-color: var(--paper);
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='160' height='160'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='.9' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='.035'/%3E%3C/svg%3E");
                color: var(--ink);
                font: 1rem/1.5 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
                -webkit-font-smoothing: antialiased;
            }

            .wrap {
                width: 100%;
                max-width: 72rem;
                margin: 0 auto;
                padding: 1.25rem 1rem;
            }

            header {
                border-bottom: 1px solid var(--line);
            }

            .site-name {
                color: inherit;
                font: 600 1.5rem/1.25 var(--display);
                letter-spacing: -0.025em;
                text-decoration: none;
            }

            main.wrap {
                flex: 1;
                padding-block: 3.5rem;
            }

            .code {
                display: inline-block;
                margin: 0;
                color: var(--accent);
                font: 600 6rem/1 var(--display);
                letter-spacing: -0.025em;
                transform: rotate(-3deg);
            }

            h1 {
                margin: 2rem 0 0;
                font: 600 2.25rem/1.2 var(--display);
                letter-spacing: -0.025em;
            }

            .message {
                max-width: 42rem;
                margin: 0.75rem 0 0;
                color: var(--muted);
                font-size: 1.125rem;
            }

            .actions {
                margin-top: 2rem;
            }

            .button {
                display: inline-flex;
                gap: 0.5rem;
                padding: 0.5rem 1rem;
                border: 1px solid var(--line);
                border-radius: 999px;
                background: var(--card);
                color: inherit;
                text-decoration: none;
                transition: border-color 0.15s;
            }

            .button:hover {
                border-color: var(--accent);
            }

            @media (min-width: 40rem) {
                main.wrap {
                    padding-block: 5rem;
                }

                .code {
                    font-size: 8rem;
                }
            }
        </style>
    </head>
    <body>
        <header>
            <div class="wrap">
                <a href="/" class="site-name">{{ $siteName }}</a>
            </div>
        </header>

        <main class="wrap">
            <p class="code" aria-hidden="true">{{ $code }}</p>
            <h1>{{ $title }}</h1>
            <p class="message">{{ $message }}</p>

            <div class="actions">
                @section('actions')
                    <a href="/" class="button"><span aria-hidden="true">←</span> Go to the home page</a>
                @show
            </div>
        </main>
    </body>
</html>
