<?php

namespace App\Http\Controllers\Site\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Confirming the email address a member signed up with, from a link sent
 * to it.
 */
class EmailVerificationController extends Controller
{
    public function notice(Request $request): View|RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->route('me');
        }

        return view('site.auth.verify-email');
    }

    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        $request->fulfill();

        return redirect()->route('me')->with('status', 'Your email address is confirmed. Welcome!');
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()?->hasVerifiedEmail()) {
            return redirect()->route('me');
        }

        $request->user()?->sendEmailVerificationNotification();

        return back()->with('status', 'A new link is on its way.');
    }
}
