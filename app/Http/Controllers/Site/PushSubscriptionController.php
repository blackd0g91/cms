<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Turning push notifications on and off in a browser, from the reminders'
 * notification switch (see reminders.ts). The browser's push service gives
 * the address and keys sent here.
 */
class PushSubscriptionController extends Controller
{
    /**
     * Browsers one member can have push notifications in.
     */
    private const int MAX_PER_MEMBER = 20;

    public function store(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'endpoint' => ['required', 'string', 'url:https', 'max:2000'],
            'keys.p256dh' => ['required', 'string', 'max:255'],
            'keys.auth' => ['required', 'string', 'max:255'],
            'contentEncoding' => ['nullable', Rule::in(['aes128gcm', 'aesgcm'])],
        ]);

        $hash = PushSubscription::hashEndpoint($validated['endpoint']);
        $subscription = PushSubscription::query()->firstOrNew(['endpoint_hash' => $hash]);

        if (! $subscription->exists && $user->pushSubscriptions()->count() >= self::MAX_PER_MEMBER) {
            throw ValidationException::withMessages(['endpoint' => 'Push notifications are on in '.self::MAX_PER_MEMBER.' browsers already. Turn them off in one first.']);
        }

        // The same browser, perhaps logged in to another account now.
        $subscription->user_id = $user->id;
        $subscription->fill([
            'endpoint' => $validated['endpoint'],
            'public_key' => $validated['keys']['p256dh'],
            'auth_token' => $validated['keys']['auth'],
            'content_encoding' => $validated['contentEncoding'] ?? 'aes128gcm',
        ])->save();

        return response()->noContent();
    }

    public function destroy(Request $request): Response
    {
        $endpoint = $request->validate(['endpoint' => ['required', 'string']])['endpoint'];

        $request->user()?->pushSubscriptions()->where('endpoint_hash', PushSubscription::hashEndpoint($endpoint))->delete();

        return response()->noContent();
    }
}
