@php
    $currentTemplate = request()->route('template');
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>

        <link rel="icon" href="/favicon.ico" sizes="any">
        <link rel="icon" href="/favicon.svg" type="image/svg+xml">

        @fonts
        @vite('resources/css/site.css')
    </head>
    <body class="flex min-h-screen flex-col bg-white font-sans text-neutral-900 antialiased dark:bg-neutral-950 dark:text-neutral-100">
        <header class="border-b border-neutral-200 dark:border-neutral-800">
            <div class="mx-auto flex h-14 max-w-6xl items-center justify-between px-4">
                <a href="{{ route('home') }}" class="text-lg font-semibold">{{ config('app.name') }}</a>

                {{-- On small screens the sidebar collapses into this menu. --}}
                <details class="group relative md:hidden">
                    <summary class="cursor-pointer list-none rounded-md px-3 py-1.5 text-sm hover:bg-neutral-100 dark:hover:bg-neutral-800">
                        Menu
                    </summary>
                    <div class="absolute right-0 z-10 mt-2 w-56 rounded-lg border border-neutral-200 bg-white p-2 shadow-lg dark:border-neutral-800 dark:bg-neutral-900">
                        @include('site.partials.nav')
                    </div>
                </details>
            </div>
        </header>

        <div class="mx-auto flex w-full max-w-6xl flex-1 gap-10 px-4 py-8">
            <aside class="hidden w-48 shrink-0 md:block">
                <div class="sticky top-8">
                    @include('site.partials.nav')
                </div>
            </aside>

            <main class="min-w-0 flex-1">
                @yield('content')
            </main>
        </div>

        <footer class="border-t border-neutral-200 dark:border-neutral-800">
            <div class="mx-auto max-w-6xl px-4 py-6 text-sm text-neutral-500">
                &copy; {{ now()->year }} {{ config('app.name') }}
            </div>
        </footer>
    </body>
</html>
