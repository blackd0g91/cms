<?php

namespace App\Http\Controllers\Site\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/**
 * Logging in on the site, for members and staff alike. Staff can also log
 * in to the control panel directly.
 */
class LoginController extends Controller
{
    public function create(): View
    {
        return view('site.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // The browser's time zone, for reminders: where they are now.
        $timezone = $request->input('timezone');

        if (is_string($timezone) && in_array($timezone, timezone_identifiers_list(\DateTimeZone::ALL), true)) {
            $request->user()?->update(['timezone' => $timezone]);
        }

        return redirect()->intended(route('me', absolute: false));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
