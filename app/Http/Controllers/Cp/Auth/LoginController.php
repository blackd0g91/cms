<?php

namespace App\Http\Controllers\Cp\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Cp\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    /**
     * Show the control panel login page.
     */
    public function create(): Response
    {
        return Inertia::render('cp/auth/Login');
    }

    /**
     * Authenticate the user and start a session.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        return redirect()->intended(route('cp.dashboard', absolute: false));
    }

    /**
     * Log the user out and invalidate the session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('cp.login');
    }
}
