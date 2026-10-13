<?php

use App\Enums\UserRole;
use App\Models\Post;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('someone created without a role is a member', function () {
    $user = User::create(['name' => 'Ana', 'email' => 'ana@example.com', 'password' => 'secret-password']);

    expect($user->fresh()->role)->toBe(UserRole::Member)
        ->and($user->isStaff())->toBeFalse();
});

test('members can not open any page of the control panel', function () {
    $template = Template::factory()->create();
    $this->actingAs(User::factory()->member()->create());

    foreach (['cp.dashboard', 'cp.health', 'cp.posts.index', 'cp.tags.index', 'cp.media.index', 'cp.trash.index', 'cp.account.edit', 'cp.users.index', 'cp.settings.edit'] as $route) {
        $this->get(route($route))->assertForbidden();
    }

    $this->get(route('cp.templates.posts.create', $template))->assertForbidden();
    $this->post(route('cp.templates.posts.store', $template), ['title' => 'Sneaky'])->assertForbidden();

    expect(Post::query()->count())->toBe(0);
});

test('a logged in member is sent from the control panel login to the site', function () {
    $this->actingAs(User::factory()->member()->create())
        ->get(route('cp.login'))
        ->assertRedirect(route('home'));
});

test('members can not log in to the control panel, even with their password', function () {
    $member = User::factory()->member()->create();

    $this->post(route('cp.login.store'), ['email' => $member->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);

    $this->assertGuest();
});

test('the users page lists staff only, and offers staff roles only', function () {
    $admin = User::factory()->create(['name' => 'Ana']);
    User::factory()->editor()->create(['name' => 'Bruno']);
    User::factory()->member()->create(['name' => 'Carla']);

    $this->actingAs($admin)
        ->get(route('cp.users.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('users', 2)
            ->where('users.0.name', 'Ana')
            ->where('users.1.name', 'Bruno')
            ->where('roles', [['value' => 'admin', 'label' => 'Admin'], ['value' => 'editor', 'label' => 'Editor']]));
});

test('staff can not be made members from the users page', function () {
    $admin = User::factory()->create();
    $editor = User::factory()->editor()->create();
    $this->actingAs($admin);

    $this->post(route('cp.users.store'), ['name' => 'Dani', 'email' => 'dani@example.com', 'role' => 'member'])
        ->assertSessionHasErrors('role');
    $this->put(route('cp.users.update', $editor), ['role' => 'member'])
        ->assertSessionHasErrors('role');

    expect(User::query()->where('email', 'dani@example.com')->exists())->toBeFalse()
        ->and($editor->fresh()->role)->toBe(UserRole::Editor);
});

test('members can not be changed, removed or sent a link from the users page', function () {
    $member = User::factory()->member()->create();
    $this->actingAs(User::factory()->create());

    $this->put(route('cp.users.update', $member), ['role' => 'admin'])->assertNotFound();
    $this->post(route('cp.users.link', $member))->assertNotFound();
    $this->delete(route('cp.users.destroy', $member))->assertNotFound();

    expect($member->fresh()->role)->toBe(UserRole::Member);
});

test('posts can only be credited to staff', function () {
    $template = Template::factory()->create();
    $admin = User::factory()->create(['name' => 'Ana']);
    User::factory()->member()->create(['name' => 'Carla']);

    $this->actingAs($admin)
        ->get(route('cp.templates.posts.create', $template))
        ->assertInertia(fn (Assert $page) => $page->where('authors', [['id' => $admin->id, 'name' => 'Ana']]));
});

test('members count as visitors in the stats, staff do not', function () {
    $post = Post::factory()->published()->for(Template::factory()->create(['handle' => 'recipes', 'fields' => []]))->create(['slug' => 'soup']);

    $this->actingAs(User::factory()->editor()->create())->get('/recipes/soup')->assertOk();
    expect(DB::table('post_views')->sum('views'))->toEqual(0);

    $this->actingAs(User::factory()->member()->create())->get('/recipes/soup')->assertOk();
    expect((int) DB::table('post_views')->where('post_id', $post->id)->sum('views'))->toBe(1);
});
