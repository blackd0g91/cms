<?php

use App\Models\Media;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

test('the dashboard shows your drafts to pick up, the templates and recent posts', function () {
    $user = User::factory()->create();
    $template = Template::factory()->create(['name' => 'Recipes']);
    $published = Post::factory()->published()->for($template)->create(['updated_at' => now()->subDay()]);
    $older = Post::factory()->for($template)->for($user, 'author')->create(['updated_at' => now()->subDays(2)]);
    $latest = Post::factory()->for($template)->for($user, 'author')->create(['summary' => 'Almost done.', 'updated_at' => now()]);
    // From before posts had authors.
    $legacy = Post::factory()->for($template)->create(['author_id' => null, 'updated_at' => now()->subDays(3)]);
    // Someone else's.
    Post::factory()->for($template)->for(User::factory()->editor(), 'author')->create(['updated_at' => now()->subHour()]);

    $this->actingAs($user)
        ->get(route('cp.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('cp/Dashboard')
            ->has('drafts', 3)
            ->where('drafts.0.id', $latest->id)
            ->where('drafts.0.description', 'Almost done.')
            ->where('drafts.0.reading_minutes', 1)
            ->where('drafts.1.id', $older->id)
            ->where('drafts.2.id', $legacy->id)
            ->where('templates.0.name', 'Recipes')
            ->has('recentPosts', 5)
            ->where('recentPosts.0.id', $latest->id)
            ->where('recentPosts.2.url', $published->url()));
});

test('the dashboard works without any content', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('cp.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('drafts', 0)
            ->has('templates', 0)
            ->where('week.top', null)
            ->where('week.lastPublished', null)
            ->has('recentPosts', 0));
});

test('the dashboard sums up the week against the week before', function () {
    $template = Template::factory()->create();
    $soup = Post::factory()->published()->for($template)->create(['title' => 'Soup', 'published_at' => now()->subDays(20)]);
    $bread = Post::factory()->published()->for($template)->create(['title' => 'Bread', 'published_at' => now()->subDays(3)]);
    Post::factory()->for($template)->create(['title' => 'Draft']);
    DB::table('post_views')->insert([
        ['post_id' => $soup->id, 'date' => now()->toDateString(), 'views' => 5],
        ['post_id' => $bread->id, 'date' => now()->subDays(6)->toDateString(), 'views' => 2],
        // The week before.
        ['post_id' => $bread->id, 'date' => now()->subDays(7)->toDateString(), 'views' => 20],
        ['post_id' => $soup->id, 'date' => now()->subDays(13)->toDateString(), 'views' => 1],
        // Older.
        ['post_id' => $soup->id, 'date' => now()->subDays(14)->toDateString(), 'views' => 50],
    ]);
    DB::table('searches')->insert([
        ['date' => now()->toDateString(), 'query' => 'soup', 'times' => 3],
        ['date' => now()->subDays(8)->toDateString(), 'query' => 'soup', 'times' => 1],
    ]);

    $this->actingAs(User::factory()->create())
        ->get(route('cp.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('week.views', ['total' => 7, 'previous' => 21])
            ->where('week.searches', ['total' => 3, 'previous' => 1])
            ->where('week.top.title', 'Soup')
            ->where('week.top.views', 5)
            ->where('week.lastPublished.title', 'Bread'));
});

test('admins are told about system warnings, which the health page explains', function () {
    $this->app['env'] = 'production';
    config(['app.debug' => true]);

    $this->actingAs(User::factory()->create())
        ->get(route('cp.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('systemWarnings', fn ($count) => $count >= 1));
});

test('the health checkup finds content worth fixing', function () {
    $template = Template::factory()->create([
        'fields' => [['handle' => 'photo', 'label' => 'Photo', 'type' => 'image', 'required' => false, 'options' => []]],
    ]);
    $withAlt = Media::factory()->create(['alt' => 'A bowl']);
    Media::factory()->create(['alt' => null]);
    $tag = Tag::factory()->create();

    $complete = Post::factory()->published()->for($template)->create(['title' => 'Complete', 'thumbnail_id' => $withAlt->id, 'data' => ['photo' => $withAlt->id]]);
    $complete->tags()->attach($tag);
    Post::factory()->for($template)->create(['title' => 'Old draft', 'updated_at' => now()->subDays(40), 'thumbnail_id' => $withAlt->id])->tags()->attach($tag);
    Post::factory()->published()->for($template)->create(['title' => 'Broken', 'thumbnail_id' => $withAlt->id, 'data' => ['photo' => 9999]])->tags()->attach($tag);
    Post::factory()->published()->for($template)->create(['title' => 'Bare']);

    $this->actingAs(User::factory()->create())
        ->get(route('cp.health'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('checkup.0.key', 'no-thumbnail')->where('checkup.0.count', 1)->where('checkup.0.items.0.title', 'Bare')
            ->where('checkup.1.key', 'no-tags')->where('checkup.1.count', 1)
            ->where('checkup.2.key', 'stale-drafts')->where('checkup.2.count', 1)->where('checkup.2.items.0.title', 'Old draft')
            ->where('checkup.3.key', 'broken-images')->where('checkup.3.count', 1)->where('checkup.3.items.0.detail', '1 missing')
            ->where('checkup.4.key', 'no-alt')->where('checkup.4.count', 1));
});

test('images inserted into markdown that were deleted count as missing', function () {
    $template = Template::factory()->create([
        'fields' => [['handle' => 'body', 'label' => 'Body', 'type' => 'markdown', 'required' => false, 'options' => []]],
    ]);
    $kept = Media::factory()->create();
    Post::factory()->for($template)->create(['data' => ['body' => "![a]({$kept->url}) ![b](/storage/media/deletedImage123.png)"]]);

    $this->actingAs(User::factory()->create())
        ->get(route('cp.health'))
        ->assertInertia(fn (Assert $page) => $page->where('checkup.3.count', 1)->where('checkup.3.items.0.detail', '1 missing'));
});

test('templates on the dashboard carry their accent color', function () {
    Template::factory()->create(['name' => 'A', 'color' => '#c2410c']);
    $automatic = Template::factory()->create(['name' => 'B', 'color' => null]);

    $this->actingAs(User::factory()->create())
        ->get(route('cp.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('templates.0.accent', '#c2410c')
            ->where('templates.1.accent', "oklch(0.58 0.13 {$automatic->hue()})"));
});

test('the dashboard shows posts published per month for the last year', function () {
    Date::setTestNow('2026-09-15 12:00:00');
    $template = Template::factory()->create();
    Post::factory()->published()->for($template)->count(2)->create(['published_at' => '2026-09-02']);
    Post::factory()->published()->for($template)->create(['published_at' => '2026-03-20']);
    Post::factory()->published()->for($template)->create(['published_at' => '2025-09-30']); // 13 months ago
    Post::factory()->for($template)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('cp.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('activity', 12)
            ->where('activity.0', ['month' => '2025-10', 'count' => 0])
            ->where('activity.5', ['month' => '2026-03', 'count' => 1])
            ->where('activity.11', ['month' => '2026-09', 'count' => 2]));
});
