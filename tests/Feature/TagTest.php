<?php

use App\Models\Post;
use App\Models\Tag;
use App\Models\Template;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->template = Template::factory()->create(['name' => 'Recipes', 'handle' => 'recipes']);
});

function savePostWithTags(Template $template, array $tags, ?Post $post = null): Post
{
    $payload = ['title' => 'Soup', 'slug' => 'soup', 'status' => 'published', 'data' => [], 'tags' => $tags];

    $post
        ? test()->put(route('cp.templates.posts.update', [$template, $post]), $payload)->assertSessionHasNoErrors()
        : test()->post(route('cp.templates.posts.store', $template), $payload)->assertSessionHasNoErrors();

    return Post::query()->where('slug', 'soup')->sole();
}

test('posts can be tagged, creating new tags and reusing existing ones', function () {
    $this->actingAs(User::factory()->create());
    Tag::factory()->create(['name' => 'Vegan', 'slug' => 'vegan']);

    $post = savePostWithTags($this->template, ['vegan', 'Quick dinners', ' quick   dinners ', '!!!']);

    expect($post->tags->pluck('name')->all())->toBe(['Quick dinners', 'Vegan'])
        ->and(Tag::count())->toBe(2)
        ->and(Tag::where('slug', 'quick-dinners')->exists())->toBeTrue();

    $post = savePostWithTags($this->template, ['Vegan'], $post);

    expect($post->fresh()->tags->pluck('name')->all())->toBe(['Vegan']);
});

test('tags are searchable', function () {
    $this->actingAs(User::factory()->create());
    savePostWithTags($this->template, ['Weeknight']);
    auth()->logout();

    $this->get(route('search', ['q' => 'weeknight']))->assertSee('Soup');
});

test('the editor gets the post tags and every existing tag', function () {
    $this->actingAs(User::factory()->create());
    Tag::factory()->create(['name' => 'Other']);
    $post = savePostWithTags($this->template, ['Vegan']);

    $this->get(route('cp.templates.posts.edit', [$this->template, $post]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('post.tags', ['Vegan'])
            ->where('allTags', ['Other', 'Vegan']));
});

test('duplicates keep their tags', function () {
    $this->actingAs(User::factory()->create());
    $post = savePostWithTags($this->template, ['Vegan']);

    $this->post(route('cp.templates.posts.duplicate', [$this->template, $post]));

    expect(Post::latest('id')->first()->tags->pluck('name')->all())->toBe(['Vegan']);
});

test('tags can be renamed and deleted in the control panel', function () {
    $this->actingAs(User::factory()->create());
    $post = savePostWithTags($this->template, ['Vegan']);
    $tag = Tag::sole();
    Tag::factory()->create(['name' => 'Taken', 'slug' => 'taken']);

    $this->get(route('cp.tags.index'))
        ->assertInertia(fn (Assert $page) => $page->component('cp/tags/Index')->where('tags.1.posts_count', 1));

    $this->put(route('cp.tags.update', $tag), ['name' => 'taken'])->assertSessionHasErrors("name.{$tag->id}");

    $this->put(route('cp.tags.update', $tag), ['name' => 'Plant based'])->assertSessionHasNoErrors();
    expect($tag->fresh())->name->toBe('Plant based')->slug->toBe('plant-based')
        ->and($post->fresh()->search_index)->toContain('plant based');

    $this->delete(route('cp.tags.destroy', $tag));
    expect($post->fresh()->tags)->toHaveCount(0)
        ->and($post->fresh()->search_index)->not->toContain('plant');
});

test('guests can not manage tags', function () {
    $this->get(route('cp.tags.index'))->assertRedirect(route('cp.login'));
});

test('tag pages list published posts only', function () {
    $tag = Tag::factory()->create(['name' => 'Vegan', 'slug' => 'vegan']);
    $published = Post::factory()->published()->for($this->template)->create(['title' => 'Salad']);
    $draft = Post::factory()->for($this->template)->create(['title' => 'Secret']);
    $published->tags()->attach($tag);
    $draft->tags()->attach($tag);
    Tag::factory()->create(['name' => 'Unused', 'slug' => 'unused']);

    $this->get('/tags/vegan')->assertOk()->assertSee('Salad')->assertDontSee('Secret')->assertSee('1 entry');
    $this->get('/tags')->assertOk()->assertSee('Vegan')->assertDontSee('Unused');
    $this->get('/tags/nope')->assertNotFound();
});

test('posts show their tags and the sidebar links to tags', function () {
    $tag = Tag::factory()->create(['name' => 'Vegan', 'slug' => 'vegan']);
    Post::factory()->published()->for($this->template)->create(['slug' => 'salad'])->tags()->attach($tag);

    $this->get('/recipes/salad')
        ->assertSee('href="'.route('site.tag', $tag).'"', false)
        ->assertSee('#Vegan')
        ->assertSee('href="'.route('site.tags').'"', false);
});

test('the sidebar has no tags link without tagged posts', function () {
    $this->get(route('home'))->assertDontSee('href="'.route('site.tags').'"', false);
});

test('tags are in the sitemap', function () {
    $tag = Tag::factory()->create(['slug' => 'vegan']);
    Post::factory()->published()->for($this->template)->create()->tags()->attach($tag);

    $this->get(route('sitemap'))->assertSee(route('site.tag', $tag));
});

test('tags is a reserved template handle', function () {
    expect(Template::RESERVED_HANDLES)->toContain('tags');
});
