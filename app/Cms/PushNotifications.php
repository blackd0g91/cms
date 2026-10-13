<?php

namespace App\Cms;

use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Throwable;

/**
 * Push notifications to the browsers members turned them on in (see
 * resources/js/reminders.ts and public/push-worker.js). The message goes to
 * each browser's push service (Google's, Mozilla's, Apple's...), encrypted
 * so only the browser can read it. Off until the keys are set in .env (see
 * config/push.php).
 */
class PushNotifications
{
    public function enabled(): bool
    {
        return filled(config('push.public_key')) && filled(config('push.private_key'));
    }

    /**
     * Send a message to every browser of the member's, forgetting the ones
     * whose push service says they are gone. How many it reached.
     *
     * @param  array{title: string, body: string, tag: string, url: string}  $message
     */
    public function send(User $user, array $message): int
    {
        $subscriptions = $user->pushSubscriptions()->get();

        if (! $this->enabled() || $subscriptions->isEmpty()) {
            return 0;
        }

        try {
            $webPush = $this->webPush();

            foreach ($subscriptions as $subscription) {
                $webPush->queueNotification(Subscription::create([
                    'endpoint' => $subscription->endpoint,
                    'publicKey' => $subscription->public_key,
                    'authToken' => $subscription->auth_token,
                    'contentEncoding' => $subscription->content_encoding,
                ]), (string) json_encode($message));
            }

            $reached = 0;

            foreach ($webPush->flush() as $report) {
                if ($report->isSuccess()) {
                    $reached++;
                } elseif ($report->isSubscriptionExpired()) {
                    PushSubscription::query()->where('endpoint_hash', PushSubscription::hashEndpoint($report->getEndpoint()))->delete();
                } else {
                    Log::warning("A push notification was not delivered: {$report->getReason()}");
                }
            }

            return $reached;
        } catch (Throwable $e) {
            Log::warning("Could not send push notifications: {$e->getMessage()}");

            return 0;
        }
    }

    /**
     * Signed with the site's keys, and kept for an hour by the push service
     * for a browser that is offline: later than that, a reminder is old news.
     */
    protected function webPush(): WebPush
    {
        return new WebPush(
            ['VAPID' => [
                'subject' => config('push.subject'),
                'publicKey' => config('push.public_key'),
                'privateKey' => config('push.private_key'),
            ]],
            ['TTL' => 3600, 'urgency' => 'high'],
        );
    }
}
