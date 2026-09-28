<?php

use App\Models\Media;
use App\Models\Post;
use App\Models\Template;
use App\Models\User;
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
