<?php

namespace App\Http\Controllers\Cp;

use App\Cms\AppIcon;
use App\Cms\PushNotifications;
use App\Http\Controllers\Controller;
use App\Models\PushMessage;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Sending a push notification with a message of your own, to one person or
 * everyone who turned notifications on in a browser (see PushNotifications).
 */
class PushMessageController extends Controller
{
    public function index(PushNotifications $push): Response
    {
        return Inertia::render('cp/notifications/Index', [
            'enabled' => $push->enabled(),
            // What shows beside the message: the site's logo (see AppIcon).
            'icon' => app(AppIcon::class)->url(),
            'recipients' => $this->recipients()
                ->withCount('pushSubscriptions')
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user) => [
                    ...$user->only(['id', 'name', 'email']),
                    'browsers' => $user->push_subscriptions_count,
                ]),
            'sent' => PushMessage::query()
                ->with(['sender:id,name', 'recipient:id,name'])
                ->latest()
                ->limit(10)
                ->get()
                ->map(fn (PushMessage $message) => [
                    ...$message->only(['id', 'to_everyone', 'title', 'body', 'url', 'people', 'reached', 'failed']),
                    'sender' => $message->sender?->name,
                    'recipient' => $message->recipient?->name,
                    'sent_at' => $message->created_at?->toIso8601String(),
                ]),
        ]);
    }

    public function store(Request $request, PushNotifications $push): RedirectResponse
    {
        abort_unless($push->enabled(), 409, 'Push notifications are off: set the keys in .env first.');

        $validated = $request->validate([
            'to' => ['required', 'string', Rule::in(['everyone', ...$this->recipients()->pluck('id')->map(fn ($id) => (string) $id)])],
            'title' => ['required', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:200'],
            // Somewhere on the site, like /recipes or /me/reminders.
            'url' => ['nullable', 'string', 'max:255', 'regex:#^/(?!/)#'],
        ], [
            'to.in' => 'Pick someone who has notifications on.',
            'url.regex' => 'Use an address on this site, starting with a slash, like /recipes.',
        ]);

        $everyone = $validated['to'] === 'everyone';
        $people = $everyone
            ? $this->recipients()->get()
            : $this->recipients()->whereKey((int) $validated['to'])->get();

        $message = new PushMessage([
            'to_everyone' => $everyone,
            'title' => trim($validated['title']),
            'body' => trim($validated['body']),
            'url' => $validated['url'] ?? '/',
            'people' => $people->count(),
        ]);
        $message->sender_id = $request->user()?->id;
        $message->recipient_id = $everyone ? null : $people->first()?->id;
        $message->save();

        $reached = 0;
        $failed = 0;

        foreach ($people as $person) {
            try {
                $results = $push->deliver($person, [
                    'title' => $message->title,
                    'body' => $message->body,
                    'tag' => "message-{$message->id}",
                    'url' => $message->url,
                ]);
            } catch (Throwable $e) {
                report($e);
                $failed += $person->pushSubscriptions()->count();

                continue;
            }

            foreach ($results as $result) {
                if ($result['delivered']) {
                    $reached++;
                } else {
                    $failed++;
                    Log::warning("A push notification to {$person->email} was not delivered: ".($result['gone'] ? 'the browser is gone' : $result['reason']));
                }
            }
        }

        $message->update(['reached' => $reached, 'failed' => $failed]);

        Inertia::flash('pushResult', ['people' => $message->people, 'reached' => $reached, 'failed' => $failed]);

        return to_route('cp.notifications.index');
    }

    /**
     * Everyone with notifications on in at least one browser.
     *
     * @return Builder<User>
     */
    private function recipients(): Builder
    {
        return User::query()->whereHas('pushSubscriptions');
    }
}
