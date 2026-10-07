<?php

use App\Cms\Settings;
use App\Enums\UserRole;
use App\Models\Post;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * The link flashed after inviting someone or asking for a new password link.
 */
function flashedLink(): string
{
    return session(SessionKey::FLASH_DATA)['link']['url'];
}

test('editors write posts and look after tags and media, but not the rest', function () {
    $this->actingAs(User::factory()->editor()->create());

    foreach (['cp.dashboard', 'cp.health', 'cp.posts.index', 'cp.tags.index', 'cp.media.index', 'cp.account.edit'] as $route) {
        $this->get(route($route))->assertOk();
    }

    foreach (['cp.templates.index', 'cp.templates.create', 'cp.links.edit', 'cp.settings.edit', 'cp.users.index'] as $route) {
        $this->get(route($route))->assertForbidden();
    }

    $this->post(route('cp.users.store'), ['name' => 'Sneaky', 'email' => 'sneaky@example.com', 'role' => 'admin'])->assertForbidden();
    expect(User::query()->where('email', 'sneaky@example.com')->exists())->toBeFalse();
});

test('only admins see the system status', function () {
    $this->actingAs(User::factory()->editor()->create())
        ->get(route('cp.health'))
        ->assertInertia(fn (Assert $page) => $page->where('system', null));
    $this->get(route('cp.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('systemWarnings', 0));

    $this->actingAs(User::factory()->create())
        ->get(route('cp.health'))
        ->assertInertia(fn (Assert $page) => $page->whereNot('system', null));
});

test('admins see everyone, with their role, posts and invitations', function () {
    $admin = User::factory()->create(['name' => 'Ana']);
    $editor = User::factory()->editor()->create(['name' => 'Bruno']);
    Post::factory()->count(2)->create(['author_id' => $editor->id]);
    $invited = User::factory()->editor()->invited()->create(['name' => 'Carla']);

    $this->actingAs($admin)->post(route('cp.users.link', $invited));

    $this->get(route('cp.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('cp/users/Index')
            ->has('users', 3)
            ->where('users.0.name', 'Ana')
            ->where('users.0.role', 'admin')
            ->where('users.0.is_me', true)
            ->where('users.1.name', 'Bruno')
            ->where('users.1.posts_count', 2)
            ->where('users.1.invited', false)
            ->where('users.1.link_expires_at', null)
            ->where('users.2.name', 'Carla')
            ->where('users.2.invited', true)
            ->whereNot('users.2.link_expires_at', null)
            ->has('roles', 2));
});

test('inviting someone gives a link to send them, which lets them choose a password and logs them in', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('cp.users.store'), ['name' => 'Dora', 'email' => 'dora@example.com', 'role' => 'editor'])
        ->assertRedirect(route('cp.users.index'));

    $dora = User::query()->where('email', 'dora@example.com')->sole();
    expect($dora->role)->toBe(UserRole::Editor)->and($dora->isInvited())->toBeTrue();

    $link = flashedLink();
    expect($link)->toStartWith(url('/cp/password/'))->toContain('email=dora%40example.com');

    // Invited, so not able to log in yet.
    auth()->logout();
    $this->post(route('cp.login.store'), ['email' => 'dora@example.com', 'password' => ''])->assertSessionHasErrors();

    $this->get($link)
        ->assertInertia(fn (Assert $page) => $page
            ->component('cp/auth/SetPassword')
            ->where('name', app(Settings::class)->siteName())
            ->where('valid', true)
            ->where('invited', true)
            ->where('user', ['name' => 'Dora', 'email' => 'dora@example.com']));

    parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
    $token = basename((string) parse_url($link, PHP_URL_PATH));

    $this->post(route('cp.password.update'), [
        'token' => $token,
        'email' => $query['email'],
        'password' => 'a-long-new-password',
        'password_confirmation' => 'a-long-new-password',
    ])->assertRedirect(route('cp.dashboard'));

    $this->assertAuthenticatedAs($dora);
    expect(Hash::check('a-long-new-password', (string) $dora->refresh()->password))->toBeTrue();

    // The link works once.
    auth()->logout();
    $this->get($link)->assertInertia(fn (Assert $page) => $page->where('valid', false)->where('user', null));
});

test('links stop working after a week, and a new one replaces the last', function () {
    $this->actingAs(User::factory()->create());
    $invited = User::factory()->invited()->create();

    $this->post(route('cp.users.link', $invited));
    $first = flashedLink();
    $this->post(route('cp.users.link', $invited));
    $second = flashedLink();
    auth()->logout();

    $this->get($first)->assertInertia(fn (Assert $page) => $page->where('valid', false));
    $this->get($second)->assertInertia(fn (Assert $page) => $page->where('valid', true));

    $this->travel(8)->days();
    $this->get($second)->assertInertia(fn (Assert $page) => $page->where('valid', false));
});

test('a password link lets someone who forgot theirs choose a new one', function () {
    $this->actingAs(User::factory()->create());
    $editor = User::factory()->editor()->create(['password' => 'the-old-password']);

    $this->post(route('cp.users.link', $editor));
    $link = flashedLink();
    expect(session(SessionKey::FLASH_DATA)['link']['invited'])->toBeFalse();
    auth()->logout();

    $this->get($link)->assertInertia(fn (Assert $page) => $page->where('valid', true)->where('invited', false));

    $this->post(route('cp.password.update'), [
        'token' => basename((string) parse_url($link, PHP_URL_PATH)),
        'email' => $editor->email,
        'password' => 'the-new-password',
        'password_confirmation' => 'the-new-password',
    ])->assertRedirect(route('cp.dashboard'));

    expect(Hash::check('the-new-password', (string) $editor->refresh()->password))->toBeTrue();
});

test('a wrong or used link does not set a password', function () {
    $invited = User::factory()->invited()->create();

    $this->post(route('cp.password.update'), [
        'token' => 'not-the-token',
        'email' => $invited->email,
        'password' => 'a-long-new-password',
        'password_confirmation' => 'a-long-new-password',
    ])->assertSessionHasErrors('password');

    $this->assertGuest();
    expect($invited->refresh()->isInvited())->toBeTrue();
});

test('admins change roles and remove people, but not themselves', function () {
    $admin = User::factory()->create();
    $editor = User::factory()->editor()->create();
    $this->actingAs($admin);

    $this->put(route('cp.users.update', $editor), ['role' => 'admin'])->assertRedirect();
    expect($editor->refresh()->role)->toBe(UserRole::Admin);

    $this->put(route('cp.users.update', $admin), ['role' => 'editor'])->assertForbidden();
    $this->delete(route('cp.users.destroy', $admin))->assertForbidden();
    $this->post(route('cp.users.link', $admin))->assertForbidden();
    expect($admin->refresh()->role)->toBe(UserRole::Admin);
});

test('removing someone keeps their posts, without an author', function () {
    $this->actingAs(User::factory()->create());
    $editor = User::factory()->editor()->create();
    $post = Post::factory()->create(['author_id' => $editor->id]);

    $this->delete(route('cp.users.destroy', $editor))->assertRedirect();

    expect(User::query()->find($editor->id))->toBeNull()
        ->and($post->refresh()->author_id)->toBeNull();
});

test('people are invited with a name and an email no one else uses', function () {
    $this->actingAs(User::factory()->create(['email' => 'taken@example.com']));

    $this->post(route('cp.users.store'), ['name' => '', 'email' => 'taken@example.com', 'role' => 'owner'])
        ->assertSessionHasErrors(['name', 'email', 'role']);
});
