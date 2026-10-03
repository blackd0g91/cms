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
            ->where('views.total', 13)
            ->has('popular', 2)
            ->where('popular.0.title', 'Salad')
            ->where('popular.0.views', 9)
            ->where('popular.1.views', 4));
});

test('the dashboard shows views per day over the last 30 days, against the 30 before', function () {
    $other = Post::factory()->published()->for($this->template)->create();
    DB::table('post_views')->insert([
        ['post_id' => $this->post->id, 'date' => now()->toDateString(), 'views' => 2],
        ['post_id' => $other->id, 'date' => now()->toDateString(), 'views' => 3],
        ['post_id' => $this->post->id, 'date' => now()->subDays(29)->toDateString(), 'views' => 1],
        // The 30 days before.
        ['post_id' => $this->post->id, 'date' => now()->subDays(30)->toDateString(), 'views' => 4],
        ['post_id' => $other->id, 'date' => now()->subDays(59)->toDateString(), 'views' => 6],
        // Older than both.
        ['post_id' => $this->post->id, 'date' => now()->subDays(60)->toDateString(), 'views' => 100],
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('cp.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('views.total', 6)
            ->where('views.previous', 10)
            ->has('views.daily', 30)
            // Oldest first, ending today, days without views included.
            ->where('views.daily.0', ['date' => now()->subDays(29)->toDateString(), 'views' => 1])
            ->where('views.daily.1', ['date' => now()->subDays(28)->toDateString(), 'views' => 0])
            ->where('views.daily.29', ['date' => now()->toDateString(), 'views' => 5]));
});

test('the post editor shows the history of the post views', function () {
    $this->post->update(['published_at' => now()->subDays(300)]);
    $other = Post::factory()->published()->for($this->template)->create();
    DB::table('post_views')->insert([
        ['post_id' => $this->post->id, 'date' => now()->subDays(3)->toDateString(), 'views' => 4],
        ['post_id' => $this->post->id, 'date' => now()->subDays(60)->toDateString(), 'views' => 20],
        ['post_id' => $this->post->id, 'date' => now()->subDays(200)->toDateString(), 'views' => 30],
        ['post_id' => $other->id, 'date' => now()->subDays(3)->toDateString(), 'views' => 50],
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('cp.templates.posts.edit', [$this->template, $this->post]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('views.last_30_days', 4)
            ->where('views.total', 54)
            ->where('views.best', ['date' => now()->subDays(200)->toDateString(), 'views' => 30])
            ->has('views.daily', 90)
            ->where('views.daily.29', ['date' => now()->subDays(60)->toDateString(), 'views' => 20])
            ->where('views.daily.86', ['date' => now()->subDays(3)->toDateString(), 'views' => 4]));
});

test('posts without views have no best day, and new posts no history', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('cp.templates.posts.edit', [$this->template, $this->post]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('views.total', 0)
            ->where('views.best', null));

    $this->get(route('cp.templates.posts.create', $this->template))
        ->assertInertia(fn (Assert $page) => $page->where('views', null));
});

test('views are deleted with their post', function () {
    app(PostViews::class)->record(request()->duplicate(server: ['HTTP_USER_AGENT' => 'Mozilla/5.0']), $this->post);
    expect(viewsOf($this->post))->toBe(1);

    $this->post->forceDelete();

    expect(DB::table('post_views')->count())->toBe(0);
});

test('a recent post has its views shown from when it was published, a week at least', function () {
    $user = User::factory()->create();

    $this->post->update(['published_at' => now()->subDays(9)->setTime(18, 30)]);
    $this->actingAs($user)
        ->get(route('cp.templates.posts.edit', [$this->template, $this->post]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('views.daily', 10)
            ->where('views.daily.0.date', now()->subDays(9)->toDateString()));

    $this->post->update(['published_at' => now()]);
    $this->get(route('cp.templates.posts.edit', [$this->template, $this->post]))
        ->assertInertia(fn (Assert $page) => $page->has('views.daily', 7));
});
