<?php

use App\Cms\PushNotifications;
use App\Models\PushMessage;
use App\Models\PushSubscription;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['push.public_key' => 'the-public-key', 'push.private_key' => 'the-private-key']);
    $this->admin = User::factory()->create(['name' => 'Admin']);
});

function signedUp(User $user, int $browsers = 1): User
{
    foreach (range(1, $browsers) as $i) {
        $subscription = new PushSubscription([
            'endpoint' => "https://push.example/{$user->id}/{$i}",
            'endpoint_hash' => PushSubscription::hashEndpoint("https://push.example/{$user->id}/{$i}"),
            'public_key' => 'key',
            'auth_token' => 'auth',
        ]);
        $subscription->user_id = $user->id;
        $subscription->save();
    }

    return $user;
}

/**
 * Pushes recorded instead of sent, each browser answering as given (true for taken).
 *
 * @param  array<int, bool>  $answers  By browser, in order, for every person; taken when missing.
 */
function recordPushes(array $answers = []): object
{
    $fake = new class($answers) extends PushNotifications
    {
        /** @var list<array{user: string, message: array<string, string>}> */
        public array $sent = [];

        public function __construct(private array $answers) {}

        public function deliver(User $user, array $message): array
        {
            $this->sent[] = ['user' => $user->name, 'message' => $message];

            return $user->pushSubscriptions()->get()->values()->map(fn (PushSubscription $subscription, int $i) => [
                'subscription' => $subscription,
                'delivered' => $this->answers[$i] ?? true,
                'gone' => false,
                'reason' => ($this->answers[$i] ?? true) ? null : 'Refused',
            ])->all();
        }
    };

    app()->instance(PushNotifications::class, $fake);

    return $fake;
}

test('the notifications page lists who has notifications on', function () {
    signedUp(User::factory()->member()->create(['name' => 'Ana', 'email' => 'ana@example.com']), 2);
    User::factory()->member()->create(['name' => 'Bruno']);

    $this->actingAs($this->admin)
        ->get(route('cp.notifications.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('cp/notifications/Index')
            ->where('enabled', true)
            ->has('recipients', 1)
            ->where('recipients.0', ['id' => User::query()->where('name', 'Ana')->value('id'), 'name' => 'Ana', 'email' => 'ana@example.com', 'browsers' => 2]));
});

test('only admins can send notifications', function () {
    foreach ([User::factory()->editor()->create(), User::factory()->member()->create()] as $user) {
        $this->actingAs($user)->get(route('cp.notifications.index'))->assertForbidden();
        $this->post(route('cp.notifications.store'), ['to' => 'everyone', 'title' => 'Hi', 'body' => 'There'])->assertForbidden();
    }
});

test('a message goes to everyone with notifications on, and is kept to look back on', function () {
    $pushes = recordPushes([true, false]);
    signedUp(User::factory()->member()->create(['name' => 'Ana']), 2);
    signedUp(User::factory()->member()->create(['name' => 'Bruno']));
    User::factory()->member()->create(['name' => 'Carla']);

    $this->actingAs($this->admin)
        ->post(route('cp.notifications.store'), ['to' => 'everyone', 'title' => ' News ', 'body' => 'A new recipe is up.', 'url' => '/recipes'])
        ->assertRedirect(route('cp.notifications.index'))
        ->assertInertiaFlash('pushResult', ['people' => 2, 'reached' => 2, 'failed' => 1]);

    $message = PushMessage::query()->sole();

    expect(collect($pushes->sent)->pluck('user')->all())->toBe(['Ana', 'Bruno'])
        ->and($pushes->sent[0]['message'])->toBe(['title' => 'News', 'body' => 'A new recipe is up.', 'tag' => "message-{$message->id}", 'url' => '/recipes'])
        ->and($message->only(['to_everyone', 'people', 'reached', 'failed', 'url']))->toBe(['to_everyone' => true, 'people' => 2, 'reached' => 2, 'failed' => 1, 'url' => '/recipes'])
        ->and($message->sender_id)->toBe($this->admin->id);

    $this->get(route('cp.notifications.index'))
        ->assertInertia(fn (Assert $page) => $page->where('sent.0.title', 'News')->where('sent.0.sender', 'Admin'));
});

test('a message can go to one person, opening the home page by default', function () {
    $pushes = recordPushes();
    $ana = signedUp(User::factory()->member()->create(['name' => 'Ana']));
    signedUp(User::factory()->member()->create(['name' => 'Bruno']));

    $this->actingAs($this->admin)
        ->post(route('cp.notifications.store'), ['to' => (string) $ana->id, 'title' => 'Hi Ana', 'body' => 'Just for you.'])
        ->assertRedirect();

    expect(collect($pushes->sent)->pluck('user')->all())->toBe(['Ana'])
        ->and($pushes->sent[0]['message']['url'])->toBe('/')
        ->and(PushMessage::query()->sole()->recipient_id)->toBe($ana->id);
});

test('messages need someone with notifications on, a title, a message and an address on the site', function () {
    recordPushes();
    $without = User::factory()->member()->create();
    $this->actingAs($this->admin);

    $this->post(route('cp.notifications.store'), ['to' => (string) $without->id, 'title' => 'Hi', 'body' => 'There'])->assertSessionHasErrors('to');
    $this->post(route('cp.notifications.store'), ['to' => 'everyone', 'title' => '', 'body' => ''])->assertSessionHasErrors(['title', 'body']);
    $this->post(route('cp.notifications.store'), ['to' => 'everyone', 'title' => 'Hi', 'body' => 'There', 'url' => 'https://elsewhere.example'])->assertSessionHasErrors('url');
    $this->post(route('cp.notifications.store'), ['to' => 'everyone', 'title' => 'Hi', 'body' => 'There', 'url' => '//elsewhere.example'])->assertSessionHasErrors('url');
    $this->post(route('cp.notifications.store'), ['to' => 'everyone', 'title' => str_repeat('a', 61), 'body' => str_repeat('a', 201)])->assertSessionHasErrors(['title', 'body']);

    expect(PushMessage::query()->count())->toBe(0);
});

test('nothing is sent while push notifications are off', function () {
    config(['push.private_key' => null]);
    signedUp(User::factory()->member()->create());

    $this->actingAs($this->admin)
        ->post(route('cp.notifications.store'), ['to' => 'everyone', 'title' => 'Hi', 'body' => 'There'])
        ->assertStatus(409);

    $this->get(route('cp.notifications.index'))->assertInertia(fn (Assert $page) => $page->where('enabled', false));
});
