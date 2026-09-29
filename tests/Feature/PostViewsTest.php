<?php

use App\Cms\PostViews;
use App\Models\Post;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->template = Template::factory()->create(['handle' => 'recipes', 'fields' => []]);
    $this->post = Post::factory()->published()->for($this->template)->create(['slug' => 'soup']);
});

function viewsOf(Post $post): int
{
    return (int) DB::table('post_views')->where('post_id', $post->id)->sum('views');
}

test('a visit counts once per visitor per day', function () {
    $this->get('/recipes/soup')->assertOk();
    $this->get('/recipes/soup');
    $this->get('/recipes/soup');

    expect(viewsOf($this->post))->toBe(1);

    // A different visitor (a fresh session) counts again.
    $this->flushSession();
    $this->get('/recipes/soup');

    expect(viewsOf($this->post))->toBe(2);

    // The same visitor counts again on another day.
    Date::setTestNow(now()->addDay());
    $this->get('/recipes/soup');

    expect(viewsOf($this->post))->toBe(3)
        ->and(DB::table('post_views')->where('post_id', $this->post->id)->count())->toBe(2);
});

test('nothing about the visitor is stored', function () {
    $this->get('/recipes/soup');

    expect(array_keys((array) DB::table('post_views')->first()))->toBe(['post_id', 'date', 'views']);
});

test('your own visits, bots and drafts are not counted', function () {
    $this->actingAs(User::factory()->create())->get('/recipes/soup');
    auth()->logout();

    foreach (['Googlebot/2.1', 'WhatsApp/2.23', 'Slackbot-LinkExpanding 1.0', 'facebookexternalhit/1.1', ''] as $agent) {
        $this->flushSession();
        $this->withHeader('User-Agent', $agent)->get('/recipes/soup');
    }

    $draft = Post::factory()->for($this->template)->create(['slug' => 'draft']);
    $this->actingAs(User::factory()->create())->get('/recipes/draft');

    expect(viewsOf($this->post))->toBe(0)->and(viewsOf($draft))->toBe(0);
});

test('the dashboard shows the most viewed posts of the last 30 days', function () {
    $other = Post::factory()->published()->for($this->template)->create(['title' => 'Salad']);
    DB::table('post_views')->insert([
        ['post_id' => $this->post->id, 'date' => now()->subDays(3)->toDateString(), 'views' => 4],
        ['post_id' => $this->post->id, 'date' => now()->subDays(60)->toDateString(), 'views' => 100],
        ['post_id' => $other->id, 'date' => now()->toDateString(), 'views' => 9],
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('cp.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('popular.total', 13)
            ->has('popular.posts', 2)
            ->where('popular.posts.0.title', 'Salad')
            ->where('popular.posts.0.views', 9)
            ->where('popular.posts.1.views', 4));

    $this->get(route('cp.templates.posts.edit', [$this->template, $this->post]))
        ->assertInertia(fn (Assert $page) => $page->where('post.views', 4));
});

test('views are deleted with their post', function () {
    app(PostViews::class)->record(request()->duplicate(server: ['HTTP_USER_AGENT' => 'Mozilla/5.0']), $this->post);
    expect(viewsOf($this->post))->toBe(1);

    $this->post->delete();

    expect(DB::table('post_views')->count())->toBe(0);
});
