<?php

use App\Models\Media;
use App\Models\Post;
use App\Models\Tag;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Inertia\Testing\AssertableInertia as Assert;

test('the dashboard shows stats, templates, recent posts and drafts', function () {
    $template = Template::factory()->create(['name' => 'Recipes']);
    $published = Post::factory()->published()->for($template)->create(['updated_at' => now()->subDay()]);
    $draft = Post::factory()->for($template)->create(['updated_at' => now()]);
    Media::factory()->count(3)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('cp.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('cp/Dashboard')
            ->where('stats', ['published' => 1, 'drafts' => 1, 'templates' => 1, 'media' => 3])
            ->where('templates.0.name', 'Recipes')
            ->where('templates.0.posts_count', 2)
            ->where('templates.0.drafts_count', 1)
            ->has('recentPosts', 2)
            ->where('recentPosts.0.id', $draft->id)
            ->where('recentPosts.0.template.name', 'Recipes')
            ->where('recentPosts.1.url', $published->url())
            ->has('drafts', 1)
            ->where('drafts.0.id', $draft->id));
});

test('the dashboard works without any content', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('cp.dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.published', 0)
            ->has('templates', 0)
            ->has('recentPosts', 0));
});

test('the dashboard checkup finds content worth fixing', function () {
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
        ->get(route('cp.dashboard'))
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
        ->get(route('cp.dashboard'))
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
