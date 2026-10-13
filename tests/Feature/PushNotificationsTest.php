<?php

use App\Cms\PushNotifications;
use App\Models\PushSubscription;
use App\Models\Reminder;
use App\Models\User;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Date;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;

beforeEach(function () {
    Date::setTestNow('2026-10-12 15:00:00');
    config(['push.public_key' => 'the-public-key', 'push.private_key' => 'the-private-key']);
    $this->member = User::factory()->member()->create();
});

function subscribe(User $user, string $endpoint = 'https://push.example/abc'): PushSubscription
{
    $subscription = new PushSubscription([
        'endpoint' => $endpoint,
        'endpoint_hash' => PushSubscription::hashEndpoint($endpoint),
        'public_key' => 'p256dh-key',
        'auth_token' => 'auth-token',
    ]);
    $subscription->user_id = $user->id;
    $subscription->save();

    return $subscription;
}

/**
 * Pushes recorded instead of sent.
 */
function fakePushes(): object
{
    $fake = new class extends PushNotifications
    {
        /** @var list<array{user: int, message: array<string, string>}> */
        public array $sent = [];

        public function send(User $user, array $message): int
        {
            $this->sent[] = ['user' => $user->id, 'message' => $message];

            return 1;
        }
    };

    app()->instance(PushNotifications::class, $fake);

    return $fake;
}

test('a browser can be signed up for push notifications, and taken off again', function () {
    $this->actingAs($this->member);

    $this->postJson(route('push-subscriptions.store'), [
        'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
        'keys' => ['p256dh' => 'the-p256dh', 'auth' => 'the-auth'],
        'contentEncoding' => 'aes128gcm',
    ])->assertNoContent();

    $subscription = $this->member->pushSubscriptions()->sole();
    expect($subscription->endpoint)->toBe('https://fcm.googleapis.com/fcm/send/abc')
        ->and($subscription->public_key)->toBe('the-p256dh')
        ->and($subscription->content_encoding)->toBe('aes128gcm');

    $this->deleteJson(route('push-subscriptions.destroy'), ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc'])->assertNoContent();
    expect($this->member->pushSubscriptions()->count())->toBe(0);
});

test('only https push addresses with their keys are taken', function () {
    $this->actingAs($this->member);

    $this->postJson(route('push-subscriptions.store'), ['endpoint' => 'http://push.example/abc', 'keys' => ['p256dh' => 'a', 'auth' => 'b']])->assertJsonValidationErrors('endpoint');
    $this->postJson(route('push-subscriptions.store'), ['endpoint' => 'https://push.example/abc'])->assertJsonValidationErrors(['keys.p256dh', 'keys.auth']);
});

test('the same browser moves to whoever logs in on it', function () {
    subscribe($this->member, 'https://push.example/shared');
    $other = User::factory()->member()->create();

    $this->actingAs($other)->postJson(route('push-subscriptions.store'), [
        'endpoint' => 'https://push.example/shared',
        'keys' => ['p256dh' => 'a', 'auth' => 'b'],
    ])->assertNoContent();

    expect(PushSubscription::query()->count())->toBe(1)
        ->and(PushSubscription::query()->sole()->user_id)->toBe($other->id);
});

test('a member can have push notifications in so many browsers', function () {
    foreach (range(1, 20) as $i) {
        subscribe($this->member, "https://push.example/{$i}");
    }

    $this->actingAs($this->member)->postJson(route('push-subscriptions.store'), [
        'endpoint' => 'https://push.example/21',
        'keys' => ['p256dh' => 'a', 'auth' => 'b'],
    ])->assertJsonValidationErrors('endpoint');
});

test('signing up for pushes needs a confirmed account', function () {
    $body = ['endpoint' => 'https://push.example/abc', 'keys' => ['p256dh' => 'a', 'auth' => 'b']];

    $this->postJson(route('push-subscriptions.store'), $body)->assertUnauthorized();
    $this->actingAs(User::factory()->member()->unverified()->create())
        ->postJson(route('push-subscriptions.store'), $body)
        ->assertForbidden();
});

test('pushes go to every browser of the member, forgetting the ones that are gone', function () {
    subscribe($this->member, 'https://push.example/working');
    subscribe($this->member, 'https://push.example/gone');

    $webPush = Mockery::mock(WebPush::class);
    $webPush->shouldReceive('queueNotification')->twice()->withArgs(fn ($subscription, $payload) => json_decode($payload, true)['body'] === 'Call the dentist');
    $webPush->shouldReceive('flush')->once()->andReturnUsing(function () {
        yield new MessageSentReport(new Request('POST', 'https://push.example/working'), new Response(201));
        yield new MessageSentReport(new Request('POST', 'https://push.example/gone'), new Response(410), false, 'Gone');
    });

    $push = Mockery::mock(PushNotifications::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $push->shouldReceive('webPush')->andReturn($webPush);

    $reached = $push->send($this->member, ['title' => 'Reminder', 'body' => 'Call the dentist', 'tag' => 'reminder-1', 'url' => '/me/reminders']);

    expect($reached)->toBe(1)
        ->and($this->member->pushSubscriptions()->pluck('endpoint')->all())->toBe(['https://push.example/working']);
});

test('pushes are off without keys', function () {
    config(['push.public_key' => null]);
    subscribe($this->member);

    expect(app(PushNotifications::class)->enabled())->toBeFalse()
        ->and(app(PushNotifications::class)->send($this->member, ['title' => 'Reminder', 'body' => 'x', 'tag' => 'x', 'url' => '/']))->toBe(0);
});

test('due reminders are pushed once, whether or not they are emailed', function () {
    $pushes = fakePushes();
    subscribe($this->member);
    $due = Reminder::factory()->for($this->member)->create(['text' => 'Stretch', 'due_at' => now()->subMinute(), 'email' => false]);
    Reminder::factory()->for($this->member)->create(['due_at' => now()->addDay()]);
    // Without a browser signed up.
    Reminder::factory()->for(User::factory()->member()->create())->create(['due_at' => now()->subMinute()]);
    // Too late.
    Reminder::factory()->for($this->member)->create(['due_at' => now()->subHours(7)]);

    $this->artisan('app:send-reminders')->assertSuccessful();
    $this->artisan('app:send-reminders')->assertSuccessful();

    expect($pushes->sent)->toBe([[
        'user' => $this->member->id,
        'message' => ['title' => 'Reminder', 'body' => 'Stretch', 'tag' => "reminder-{$due->id}", 'url' => '/me/reminders'],
    ]]);

    // Pushed again for its next time.
    $due->update(['due_at' => now()->addHour()]);
    Date::setTestNow(now()->addHour()->addMinute());
    $this->artisan('app:send-reminders');

    expect($pushes->sent)->toHaveLength(2);
});

test('the notification switch knows the public key, when pushes are set up', function () {
    $this->actingAs($this->member);

    $this->get(route('reminders.index'))
        ->assertSee('data-push-key="the-public-key"', false)
        ->assertSee('data-push-url="'.route('push-subscriptions.store').'"', false);

    config(['push.private_key' => null]);
    $this->get(route('reminders.index'))->assertSee('data-push-key=""', false);
});

test('the site has an app manifest, for phones\' home screens', function () {
    $this->get(route('manifest'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('start_url', '/');

    $this->get(route('home'))->assertSee('<link rel="manifest" href="/manifest.webmanifest">', false);
});

test('push keys are made by a command', function () {
    $this->artisan('app:push-keys')
        ->expectsOutputToContain('WEBPUSH_PUBLIC_KEY=')
        ->expectsOutputToContain('WEBPUSH_PRIVATE_KEY=')
        ->assertSuccessful();
});

test('the service worker shows pushes and opens the site', function () {
    $worker = file_get_contents(public_path('push-worker.js'));

    expect($worker)->toContain("addEventListener('push'")->toContain('showNotification')->toContain("addEventListener('notificationclick'");
});
