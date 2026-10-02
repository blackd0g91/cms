@php
    // On a not found page this can be the handle that matched no template.
    $currentTemplate = request()->route('template');
    $currentTemplate = $currentTemplate instanceof \App\Models\Template ? $currentTemplate : null;
    $siteName = $settings->siteName();
    $profiles = $settings->profiles();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @include('partials.theme')
        <title>{{ isset($title) ? $title.' - '.$siteName : $siteName }}</title>
        @include('site.partials.meta')

        @include('partials.icons')

        @fonts
        @vite(['resources/css/site.css', 'resources/js/site.ts'])
    </head>
    <body class="flex min-h-screen flex-col bg-paper font-sans text-ink antialiased">
        <header class="border-b border-line">
            <div class="mx-auto flex max-w-6xl flex-wrap items-center gap-x-4 gap-y-3 px-4 py-5">
                <a href="{{ route('home') }}" class="group flex min-w-0 flex-1 items-center gap-3 sm:flex-none">
                    @php($logoBackground = $settings->logoBackground())
                    @if ($logo = $settings->logo())
                        @if ($logoBackground)
                            <span class="grid h-10 min-w-10 shrink-0 place-items-center rounded-xl p-1.5 shadow-sm" style="{{ $logoBackground }}">
                                <img src="{{ $logo->url }}" alt="" class="h-7 w-auto max-w-36 object-contain">
                            </span>
                        @else
                            <img src="{{ $logo->url }}" alt="" class="h-10 w-auto max-w-40 shrink-0 object-contain">
                        @endif
                    @else
                        <span
                            @class([
                                'grid size-10 shrink-0 place-items-center rounded-full font-display text-xl font-semibold transition group-hover:-rotate-6',
                                'bg-ink text-paper' => ! $logoBackground,
                                'text-white shadow-sm' => $logoBackground,
                            ])
                            @if ($logoBackground) style="{{ $logoBackground }}" @endif
                        >
                            {{ Str::upper(Str::substr($siteName, 0, 1)) }}
                        </span>
                    @endif
                    <span class="min-w-0">
                        <span class="block truncate font-display text-2xl leading-tight font-semibold tracking-tight">{{ $siteName }}</span>
                        @if ($settings->get('tagline'))
                            <span class="block truncate font-mono text-xs text-muted">{{ $settings->get('tagline') }}</span>
                        @endif
                    </span>
                </a>

                {{-- Beside the name it shrinks before anything else wraps, like when there are profile icons. --}}
                <form action="{{ route('search') }}" method="get" role="search" class="relative order-last w-full sm:order-none sm:ml-auto sm:w-auto sm:max-w-xs sm:min-w-36 sm:flex-1">
                    <label for="site-search" class="sr-only">Search</label>
                    <input
                        id="site-search"
                        type="search"
                        name="q"
                        value="{{ request()->routeIs('search') ? request('q') : '' }}"
                        placeholder="Search"
                        class="w-full rounded-full border border-line bg-card py-2 pr-10 pl-4 text-sm placeholder:text-muted focus:border-accent focus:ring-4 focus:ring-accent-soft focus:outline-none"
                    >
                    <kbd class="pointer-events-none absolute top-1/2 right-3 hidden -translate-y-1/2 rounded border border-line px-1.5 font-mono text-[10px] text-muted sm:block">/</kbd>
                </form>

                {{-- On small screens they move into the menu. --}}
                @if ($profiles)
                    <div class="hidden md:block">
                        @include('site.partials.profiles')
                    </div>
                @endif

                <button
                    type="button"
                    data-theme-toggle
                    hidden
                    class="grid size-10 shrink-0 place-items-center rounded-full border border-line bg-card text-muted transition hover:border-accent hover:text-ink"
                >
                    {{-- site.ts shows the icon for the current choice. --}}
                    <svg data-theme-icon="system" class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="12" rx="2"/><path d="M8 20h8M12 16v4"/></svg>
                    <svg data-theme-icon="light" class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                    <svg data-theme-icon="dark" class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden><path d="M20 14.5A8 8 0 0 1 9.5 4a8 8 0 1 0 10.5 10.5z"/></svg>
                </button>

                {{-- On small screens the sidebar collapses into this menu. --}}
                <details class="relative shrink-0 md:hidden">
                    <summary class="cursor-pointer list-none rounded-full border border-line bg-card px-4 py-2 text-sm">
                        Menu
                    </summary>
                    <div class="absolute right-0 z-10 mt-2 w-64 rounded-xl border border-line bg-card p-3 shadow-xl">
                        @include('site.partials.nav')

                        @if ($profiles)
                            <div class="mt-4 border-t border-line pt-3">
                                @include('site.partials.profiles')
                            </div>
                        @endif
                    </div>
                </details>
            </div>
        </header>

        <div class="mx-auto flex w-full max-w-6xl flex-1 gap-12 px-4 py-10">
            {{--
                A card that stays in view while the page scrolls, like "On this
                page", with the links at its bottom. It is as tall as the screen
                without the header (81px), the footer (65px) and the space above
                and below it (2.5rem each), and scrolls inside if that is not
                enough. On pages shorter than the screen it fills the column.
            --}}
            <aside class="hidden w-56 shrink-0 md:block">
                <div class="panel-scrollbar sticky top-10 flex h-full max-h-[calc(100vh-81px-65px-5rem)] flex-col overflow-y-auto rounded-2xl border border-line bg-card p-3">
                    @include('site.partials.nav')
                </div>
            </aside>

            <main class="min-w-0 flex-1">
                @yield('content')
            </main>
        </div>

        <footer class="border-t border-line">
            <div class="mx-auto flex max-w-6xl items-center justify-between gap-4 px-4 py-6 font-mono text-xs text-muted">
                <span>{{ $settings->footer() }}</span>
                <a href="#" class="hover:text-ink">Back to top ↑</a>
            </div>
        </footer>
    </body>
</html>
