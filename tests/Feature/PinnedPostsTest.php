<?php

use App\Models\Post;
use App\Models\Tag;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->template = Template::factory()->create(['name' => 'Recipes', 'handle' => 'recipes', 'fields' => []]);
});

function publishPost(array $attributes): Post
{
    return Post::factory()->published()->for(test()->template)->create($attributes);
}

test('pinned posts come first on listings', function () {
    $tag = Tag::factory()->create(['slug' => 'quick']);
    publishPost(['title' => 'Newest', 'published_at' => now()->subHour()])->tags()->attach($tag);
    publishPost(['title' => 'Old but pinned', 'published_at' => now()->subYear(), 'pinned_at' => now()->subDay()])->tags()->attach($tag);
    publishPost(['title' => 'Pinned last', 'published_at' => now()->subMonth(), 'pinned_at' => now()])->tags()->attach($tag);

    $order = ['Pinned last', 'Old but pinned', 'Newest'];

    $this->get(route('home'))->assertSeeInOrder($order);
    $this->get('/recipes')->assertSeeInOrder($order)->assertSee('Pinned');
    $this->get('/tags/quick')->assertSeeInOrder($order);
});

test('the editor pins and unpins posts', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('cp.templates.posts.store', $this->template), [
        'title' => 'Soup', 'status' => 'published', 'data' => [], 'pinned' => true,
    ])->assertSessionHasNoErrors();

    $post = Post::sole();
    $pinnedAt = $post->pinned_at;
    expect($pinnedAt)->not->toBeNull();

    // Saving again keeps when it was pinned; unpinning clears it.
    Date::setTestNow(now()->addMinute());
    $payload = ['title' => 'Soup', 'slug' => $post->slug, 'status' => 'published', 'data' => []];

    $this->put(route('cp.templates.posts.update', [$this->template, $post]), [...$payload, 'pinned' => true]);
    expect($post->fresh()->pinned_at->equalTo($pinnedAt))->toBeTrue();

    $this->put(route('cp.templates.posts.update', [$this->template, $post]), [...$payload, 'pinned' => false]);
    expect($post->fresh()->pinned_at)->toBeNull();
});

test('control panel lists and the editor show pinned posts', function () {
    $this->actingAs(User::factory()->create());
    $post = publishPost(['pinned_at' => now()]);

    $this->get(route('cp.posts.index'))->assertInertia(fn (Assert $page) => $page->where('posts.data.0.pinned', true));
    $this->get(route('cp.dashboard'))->assertInertia(fn (Assert $page) => $page->where('recentPosts.0.pinned', true));
    $this->get(route('cp.templates.posts.edit', [$this->template, $post]))->assertInertia(fn (Assert $page) => $page->where('post.pinned', true));
});

test('duplicates are not pinned', function () {
    $this->actingAs(User::factory()->create());
    $post = publishPost(['pinned_at' => now()]);

    $this->post(route('cp.templates.posts.duplicate', [$this->template, $post]));

    expect(Post::latest('id')->first()->pinned_at)->toBeNull();
});
