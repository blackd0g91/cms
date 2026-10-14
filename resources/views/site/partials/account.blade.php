{{--
    In the header, round buttons like the theme one: a link to log in, or,
    logged in, their own page (with their initials), a reminder to confirm
    their email, the account, the control panel for staff, and logging out.
--}}
@php
    $badge = 'grid size-10 shrink-0 place-items-center rounded-full border bg-card transition hover:border-accent';
    $plain = $badge.' border-line text-muted hover:text-ink';
@endphp
@auth
    @php
        $user = auth()->user();
        $initials = Str::of($user->name)->squish()->explode(' ')->take(2)->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))->implode('');
    @endphp
    <a href="{{ route('me') }}" title="Your page ({{ $user->name }}, {{ $user->email }})" class="{{ $badge }} border-line text-sm font-semibold">
        <span aria-hidden="true">{{ $initials }}</span>
        <span class="sr-only">Your page</span>
    </a>
    @unless ($user->hasVerifiedEmail())
        <a href="{{ route('verification.notice') }}" title="Confirm your email" class="{{ $badge }} relative border-accent text-accent">
            <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
            <span class="absolute top-0 right-0 size-2.5 rounded-full bg-accent ring-2 ring-card" aria-hidden="true"></span>
            <span class="sr-only">Confirm your email</span>
        </a>
    @endunless
    <a href="{{ route('account.edit') }}" title="Your account" class="{{ $plain }}">
        <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1z"/></svg>
        <span class="sr-only">Your account</span>
    </a>
    @if ($user->isStaff())
        <a href="{{ route('cp.dashboard') }}" title="Control panel" class="{{ $plain }}">
            <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
            <span class="sr-only">Control panel</span>
        </a>
    @endif
    <form method="post" action="{{ route('logout') }}" class="contents">
        @csrf
        <button type="submit" title="Log out" class="{{ $plain }} cursor-pointer">
            <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/></svg>
            <span class="sr-only">Log out</span>
        </button>
    </form>
@else
    <a href="{{ route('login') }}" title="Log in" class="{{ $plain }}">
        <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
        <span class="sr-only">Log in</span>
    </a>
@endauth
