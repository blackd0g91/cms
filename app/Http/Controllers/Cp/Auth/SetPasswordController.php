<?php

namespace App\Http\Controllers\Cp\Auth;

use App\Cms\PasswordLinks;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Choosing a password from a link made on the users page, which then logs
 * you in: to accept an invitation, or when you forgot your password.
 */
class SetPasswordController extends Controller
{
    public function __construct(private PasswordLinks $links) {}

    public function edit(Request $request, string $token): Response
    {
        $user = User::query()->where('email', (string) $request->query('email'))->first();
        $valid = $user !== null && $this->links->isValid($user, $token);

        return Inertia::render('cp/auth/SetPassword', [
            'valid' => $valid,
            'token' => $token,
            // Not "name", which is the site's name on every page.
            'user' => $valid ? $user->only(['name', 'email']) : null,
            'invited' => $valid && $user->isInvited(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $this->links->use($validated['email'], $validated['token'], $validated['password']);

        if ($user === null) {
            throw ValidationException::withMessages([
                'password' => 'This link has expired or was already used. Ask an admin for a new one.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return to_route('cp.dashboard');
    }
}
