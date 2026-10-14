{{--
    In the header: a link to log in, or, logged in, a menu with their own
    page, the account, the control panel for staff, and logging out.
--}}
@auth
    @php
        $user = auth()->user();
        $initials = Str::of($user->name)->squish()->explode(' ')->take(2)->map(fn ($word) => Str::upper(Str::substr($word, 0, 1)))->implode('');
    @endphp
    <details data-popover class="relative">
        <summary
            title="{{ $user->name }}"
            class="grid size-10 cursor-pointer list-none place-items-center rounded-full border border-line bg-card text-sm font-semibold transition hover:border-accent [&::-webkit-details-marker]:hidden"
        >
            <span aria-hidden="true">{{ $initials }}</span>
            <span class="sr-only">Your account</span>
        </summary>
        <div class="absolute right-0 z-20 mt-2 w-64 rounded-2xl border border-line bg-card p-2 text-sm shadow-xl">
            <p class="px-3 pt-2 pb-3">
                <span class="block truncate font-medium">{{ $user->name }}</span>
                <span class="block truncate text-xs text-muted">{{ $user->email }}</span>
            </p>
            @unless ($user->hasVerifiedEmail())
                <a href="{{ route('verification.notice') }}" class="flex items-center gap-2 rounded-lg px-3 py-2 text-accent transition hover:bg-paper">
                    <span class="size-2 rounded-full bg-accent" aria-hidden="true"></span>
                    Confirm your email
                </a>
            @endunless
            <a href="{{ route('me') }}" class="block rounded-lg px-3 py-2 font-medium transition hover:bg-paper">Your page</a>
            <a href="{{ route('account.edit') }}" class="block rounded-lg px-3 py-2 transition hover:bg-paper">Your account</a>
            @if ($user->isStaff())
                <a href="{{ route('cp.dashboard') }}" class="block rounded-lg px-3 py-2 transition hover:bg-paper">Control panel</a>
            @endif
            <form method="post" action="{{ route('logout') }}" class="border-t border-line pt-1 mt-1">
                @csrf
                <button type="submit" class="w-full rounded-lg px-3 py-2 text-left text-muted transition hover:bg-paper hover:text-ink">Log out</button>
            </form>
        </div>
    </details>
@else
    <a
        href="{{ route('login') }}"
        title="Log in"
        class="grid size-10 shrink-0 place-items-center rounded-full border border-line bg-card text-muted transition hover:border-accent hover:text-ink"
    >
        <svg class="size-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
        <span class="sr-only">Log in</span>
    </a>
@endauth
