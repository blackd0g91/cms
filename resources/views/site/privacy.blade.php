{{--
    What the site keeps about visitors and members. Keep it in step with what
    the code does: accounts (Site\AccountController), the stats (app/Cms:
    PostViews, SiteSearches, LinkClicks, Referrers, DailyVisitor) and the home
    page cards (weather.ts, PictureOfTheDay, FreeGames), and the weather location
    kept in accounts, reminders (Site\ReminderController) and push
    notifications (Site\PushSubscriptionController).
--}}
@extends('site.layout', ['title' => 'Privacy'])

@section('content')
    <article class="post-surface prose max-w-2xl">
        <h1>Privacy</h1>
        <p class="lead">What {{ app(\App\Cms\Settings::class)->siteName() }} keeps about you, and why. In short: as little as it can.</p>

        <h2>Visiting</h2>
        <ul>
            <li>The site counts how often posts are read, what is searched for, which links are followed and which sites people come from. These are only daily totals, like "12 views today": nothing in them says who you are.</li>
            <li>So that one person counts once a day, the site keeps a session cookie, and a scrambled (hashed) form of your internet address for a day at most. Neither is ever shown or kept longer.</li>
            <li>The site does not use advertising or tracking cookies, and does not load anything from analytics services. Its fonts and scripts come from the site itself.</li>
        </ul>

        <h2>Your account</h2>
        <ul>
            <li>With an account, the site keeps your name, your email address, whether you confirmed it, and your password, scrambled so that nobody can read it. If you show your weather while logged in, it also keeps that place, rounded to about a kilometer, and if you choose the cards on your page, which ones and in what order.</li>
            <li>Your reminders are kept with your account, with your time zone (from your browser) so they come at your time. Only you can see them.</li>
            <li>If you turn on notifications in a browser, the site keeps the address that browser's push service (Google's, Mozilla's or Apple's, depending on the browser) gave it, to send your reminders there. The messages are encrypted so that only your browser can read them. Turning notifications off, or deleting your account, removes it.</li>
            <li>Your email address is used only for your account: to confirm it, and to send a link when you forget your password. Those emails go out through a mail service, which sees the address and the email.</li>
            <li>Logging in keeps a cookie in your browser, so the site knows it is you.</li>
            <li>You can change your name, address and password, or <a href="{{ route('account.edit') }}">delete your account</a>, at any time. Deleting it deletes everything in it, straight away.</li>
        </ul>

        <h2>The cards on the home page</h2>
        <ul>
            <li><strong>Weather:</strong> only when you ask for it, your browser finds your location, rounds it to about a kilometer, and asks Open-Meteo for the weather and BigDataCloud for the place's name. The location stays in your browser, and when you are logged in, in your account too, so your other devices can start from it. You can forget it from the weather card or your account page.</li>
            <li><strong>Picture of the day</strong> and <strong>free games:</strong> the pictures are loaded from Wikimedia and GamerPower, so those sites see that your browser asked for them, as with any picture from another site.</li>
        </ul>

        <p class="text-sm text-muted">Last changed October 13, 2026.</p>
    </article>
@endsection
