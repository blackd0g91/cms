<?php

namespace App\Http\Controllers\Site\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Signing up on the site, as a member: they are logged in straight away and
 * sent an email to confirm their address.
 */
class RegisterController extends Controller
{
    public function create(): View
    {
        return view('site.auth.register');
    }

    public function store(Request $request): RedirectResponse
    {
        // One address, however it was typed.
        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'confirmed', Password::defaults()],
            // Hidden from people (see register.blade.php): bots that fill in
            // every field give themselves away.
            'website' => ['prohibited'],
        ]);

        $user = User::create([
            'name' => Str::squish($validated['name']),
            'email' => $validated['email'],
            'password' => $validated['password'],
            'role' => UserRole::Member,
        ]);

        // Sends the email to confirm the address.
        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('verification.notice');
    }
}
