<?php

namespace App\Http\Controllers\Site;

use App\Enums\HomeWidget;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Your account on the site: name, email address and password, the cards on
 * their own page, the weather location, and deleting it with everything in
 * it. Staff can change theirs here too, but not delete it: an admin removes
 * them from the control panel.
 */
class AccountController extends Controller
{
    public function edit(Request $request): View
    {
        return view('site.account.edit', ['user' => $request->user()]);
    }

    /**
     * A new address has to be confirmed again, from a link sent to it.
     */
    public function update(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        $request->merge(['email' => Str::lower(trim((string) $request->input('email')))]);

        $validated = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
        ]);

        $user->fill(['name' => Str::squish($validated['name']), 'email' => $validated['email']]);
        $emailChanged = $user->isDirty('email');

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();

            return redirect()->route('verification.notice');
        }

        return redirect()->route('account.edit')->with('status', 'Your details are saved.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        /** @var User $user */
        $user = $request->user();
        $user->update(['password' => $validated['password']]);

        return redirect()->route('account.edit')->with('status', 'Your new password is set.');
    }

    /**
     * The cards at the top of their own page: the ones ticked, in the order
     * they were sent (see sortable.ts), none at all, or back to the defaults.
     */
    public function updateWidgets(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($request->boolean('reset')) {
            $user->update(['home_widgets' => null]);

            return redirect()->route('account.edit')->with('status', 'Your page has the usual cards again.');
        }

        $validated = $request->validateWithBag('widgets', [
            'widgets' => ['sometimes', 'array'],
            'widgets.*' => ['distinct', Rule::enum(HomeWidget::class)],
        ]);

        $user->update(['home_widgets' => array_values($validated['widgets'] ?? [])]);

        return redirect()->route('account.edit')->with('status', 'Your cards are saved.');
    }

    /**
     * Where they last asked for the weather, from the weather card (see
     * weather.ts), so their other devices can start from it. Rounded again
     * here: never more exact than about a kilometer.
     */
    public function updateLocation(Request $request): Response
    {
        $validated = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'name' => ['nullable', 'string', 'max:100'],
        ]);

        $request->user()?->update(['location' => [
            'latitude' => round((float) $validated['latitude'], 2),
            'longitude' => round((float) $validated['longitude'], 2),
            'name' => $validated['name'] ?? null,
        ]]);

        return response()->noContent();
    }

    public function destroyLocation(Request $request): Response|RedirectResponse
    {
        $request->user()?->update(['location' => null]);

        // From the account page, rather than the weather card.
        return $request->expectsJson()
            ? response()->noContent()
            : redirect()->route('account.edit')->with('status', 'Your weather location is forgotten.');
    }

    /**
     * Deleting a member's account deletes everything in it, for good.
     */
    public function destroy(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        abort_if($user->isStaff(), 403, 'Staff accounts are removed by an admin, from the control panel.');

        $request->validateWithBag('deletion', [
            'password' => ['required', 'current_password'],
        ]);

        Auth::guard('web')->logout();

        DB::transaction(function () use ($user) {
            // Logged in elsewhere too, those sessions end with the account.
            if (config('session.driver') === 'database') {
                DB::table(config('session.table'))->where('user_id', $user->id)->delete();
            }

            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            $user->delete();
        });

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status', 'Your account is deleted, with everything in it.');
    }
}
