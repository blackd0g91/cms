{{--
    The notifications switch as a round bell button, for the home page's
    panel: the same switch as site/reminders/notify.blade.php (see
    startNotifyToggle in reminders.ts), with what it does in its tooltip. The
    bell is filled, with a dot, when notifications are on in this browser.
--}}
@php
    $push = app(\App\Cms\PushNotifications::class);
    $buttonClass = 'relative grid size-8 shrink-0 place-items-center rounded-full border border-line transition';
    $bell = '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0"/>';
@endphp
<div
    data-reminder-notify
    data-push-key="{{ $push->enabled() ? config('push.public_key') : '' }}"
    data-push-url="{{ route('push-subscriptions.store') }}"
    hidden
    class="contents"
>
    <button type="button" data-notify-state="off" data-notify-on hidden title="Notify me in this browser" class="{{ $buttonClass }} text-muted hover:border-accent hover:text-ink">
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $bell !!}</svg>
        <span class="sr-only">Notify me in this browser</span>
    </button>

    <span data-notify-state="on" hidden class="contents">
        @foreach (['push' => 'even with the site closed', 'page' => 'while a page of the site is open'] as $mode => $when)
            <button
                type="button"
                data-notify-mode="{{ $mode }}"
                data-notify-off
                @if ($mode === 'push') hidden @endif
                title="Notifications on in this browser, {{ $when }}. Click to turn them off."
                class="{{ $buttonClass }} border-accent text-accent hover:bg-accent-soft"
            >
                <svg class="size-4" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $bell !!}</svg>
                <span class="absolute -top-0.5 -right-0.5 size-2.5 rounded-full bg-accent ring-2 ring-card" aria-hidden="true"></span>
                <span class="sr-only">Notifications on in this browser, {{ $when }}. Turn them off</span>
            </button>
        @endforeach
    </span>

    {{-- A page can not ask again once blocked: the tooltip says where to allow them. --}}
    <button type="button" data-notify-state="blocked" hidden aria-disabled="true" title="Notifications are blocked for this site. Allow them in your browser's site settings to get them." class="{{ $buttonClass }} cursor-help text-muted/60">
        <svg class="size-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $bell !!}<path d="M3 3l18 18"/></svg>
        <span class="sr-only">Notifications are blocked for this site. Allow them in your browser's site settings to get them.</span>
    </button>
</div>
