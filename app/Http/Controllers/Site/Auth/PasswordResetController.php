<?php

namespace App\Http\Controllers\Site\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\View\View;

/**
 * A forgotten password: an emailed link to choose a new one, which then
 * logs you in.
 */
class PasswordResetController extends Controller
{
    public function request(): View
    {
        return view('site.auth.forgot-password');
    }

    /**
     * The answer is the same whether there is an account for the address or
     * not, so the form does not tell which addresses have one.
     */
    public function email(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'string', 'email']]);

        Password::sendResetLink($request->only('email'));

        return back()->with('status', 'If there is an account for that address, a link to choose a new password is on its way. It works for an hour.');
    }

    public function edit(Request $request, string $token): View
    {
        return view('site.auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    // The link came to their inbox, so the address is theirs.
                    'email_verified_at' => $user->email_verified_at ?? now(),
                ])->save();

                event(new PasswordReset($user));

                Auth::login($user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => 'This link has expired or was already used. Ask for a new one below.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('me')->with('status', 'Your new password is set, and you are logged in.');
    }
}
