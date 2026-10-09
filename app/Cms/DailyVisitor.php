<?php

namespace App\Cms;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Whether something a visitor did today (a visit, a click) should count:
 * once per visitor, as the session tells, and only so many times per address.
 * Visitors who drop the session cookie, like scripts, look new on every
 * request, so the address cap is what stops them from adding up counts.
 */
class DailyVisitor
{
    private const int DAY = 86400;

    /**
     * Whether this is the visitor's first $key today, counting it if so.
     * An address counts at most $perAddress times a day for a key, which
     * leaves room for a household or an office behind one address.
     */
    public static function first(Request $request, string $key, int $perAddress = 5): bool
    {
        $today = now()->toDateString();
        $sessionKey = "{$key}.{$today}";

        if ($request->hasSession() && $request->session()->get($sessionKey)) {
            return false;
        }

        if (! self::withinLimit($request, "{$key}.{$today}", $perAddress)) {
            return false;
        }

        if ($request->hasSession()) {
            $request->session()->put($sessionKey, true);
        }

        return true;
    }

    /**
     * Count one more for the address under $key, if it has had fewer than
     * $max today. Only a hash of the address is kept, and only for a day.
     */
    public static function withinLimit(Request $request, string $key, int $max): bool
    {
        $limiter = 'daily-visitor.'.hash('sha256', $key.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($limiter, $max)) {
            return false;
        }

        RateLimiter::hit($limiter, self::DAY);

        return true;
    }
}
