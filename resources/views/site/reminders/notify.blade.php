{{--
    Turning system notifications for reminders on or off in this browser
    (see startNotifyToggle in reminders.ts, which shows the part for the
    browser's state). With push notifications set up (config/push.php), they
    come even with the site closed. Hidden without the script, and in
    browsers that have no notifications.
--}}
@php($push = app(\App\Cms\PushNotifications::class))
<div
    data-reminder-notify
    data-push-key="{{ $push->enabled() ? config('push.public_key') : '' }}"
    data-push-url="{{ route('push-subscriptions.store') }}"
    hidden
    class="text-xs text-muted"
>
    <p data-notify-state="off" hidden>
        <button type="button" data-notify-on class="inline-flex items-center gap-1.5 underline-offset-4 hover:text-ink hover:underline">
            <svg class="size-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
            Notify me in this browser
        </button>
    </p>
    <p data-notify-state="on" hidden>
        <span class="inline-flex items-center gap-1.5">
            <span class="size-1.5 rounded-full bg-accent" aria-hidden="true"></span>
            Notifications on in this browser,
            <span data-notify-mode="push" hidden>even with the site closed.</span>
            <span data-notify-mode="page">while a page of the site is open.</span>
        </span>
        <button type="button" data-notify-off class="underline underline-offset-4 hover:text-ink">Turn off</button>
    </p>
    <p data-notify-state="blocked" hidden>
        Notifications are blocked for this site. Allow them in your browser's site settings to get them.
    </p>
</div>
