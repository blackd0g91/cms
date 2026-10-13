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
        if (! $this->enabled()) {
            return 0;
        }

        try {
            $results = $this->deliver($user, $message);
        } catch (Throwable $e) {
            Log::warning("Could not send push notifications: {$e->getMessage()}");

            return 0;
        }

        foreach ($results as $result) {
            if (! $result['delivered'] && ! $result['gone']) {
                Log::warning("A push notification was not delivered: {$result['reason']}");
            }
        }

        return count(array_filter($results, fn (array $result) => $result['delivered']));
    }

    /**
     * Send a message to every browser of the member's, and what each one's
     * push service answered: taken (on its way to the browser), refused (with
     * why), or gone, in which case the browser is forgotten.
     *
     * @param  array{title: string, body: string, tag: string, url: string}  $message
     * @return list<array{subscription: PushSubscription, delivered: bool, gone: bool, reason: string|null}>
     */
    public function deliver(User $user, array $message): array
    {
        $subscriptions = $user->pushSubscriptions()->get()->keyBy('endpoint');

        if ($subscriptions->isEmpty()) {
            return [];
        }

        $webPush = $this->webPush();

        foreach ($subscriptions as $subscription) {
            $webPush->queueNotification(Subscription::create([
                'endpoint' => $subscription->endpoint,
                'publicKey' => $subscription->public_key,
                'authToken' => $subscription->auth_token,
                'contentEncoding' => $subscription->content_encoding,
            ]), (string) json_encode($message));
        }

        $results = [];

        foreach ($webPush->flush() as $report) {
            $subscription = $subscriptions->get($report->getEndpoint());

            if (! $subscription) {
                continue;
            }

            $gone = ! $report->isSuccess() && $report->isSubscriptionExpired();

            if ($gone) {
                $subscription->delete();
            }

            $results[] = [
                'subscription' => $subscription,
                'delivered' => $report->isSuccess(),
                'gone' => $gone,
                'reason' => $report->isSuccess() ? null : $report->getReason(),
            ];
        }

        return $results;
    }

    /**
     * Which push service a browser's address belongs to, for people.
     */
    public static function service(PushSubscription $subscription): string
    {
        $host = (string) parse_url($subscription->endpoint, PHP_URL_HOST);

        return match (true) {
            str_ends_with($host, 'googleapis.com') => 'Chrome, Edge or Android',
            str_ends_with($host, 'mozilla.com') => 'Firefox',
            str_ends_with($host, 'push.apple.com') => 'Safari or iPhone',
            str_ends_with($host, 'notify.windows.com') => 'Edge (Windows)',
            default => $host,
        };
    }

    /**
     * Signed with the site's keys, and kept for an hour by the push service
     * for a browser that is offline: later than that, a reminder is old news.
     * Its warnings about the server (such as a missing GMP or BCMath extension
     * on production) go to the log: raised as PHP notices, Laravel would turn
     * them into errors and nothing would be sent.
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
            logger: Log::channel(),
        );
    }
}
