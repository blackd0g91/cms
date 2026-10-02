<?php

use App\Models\Post;
use App\Models\Tag;
use App\Models\Template;

beforeEach(function () {
    $this->template = Template::factory()->create(['name' => 'Recipes', 'handle' => 'recipes', 'fields' => [], 'layout' => '']);
});

/**
 * Published posts "Post 01" (oldest) to "Post 25" (newest).
 */
function manyPosts(Template $template, int $count = 25): void
{
    foreach (range(1, $count) as $i) {
        Post::factory()->published()->for($template)->create([
            'title' => sprintf('Post %02d', $i),
            'published_at' => now()->subDays($count - $i),
        ]);
    }
}

test('a template shows 24 posts a page, newest first, with a link to the next page', function () {
    manyPosts($this->template);

    $this->get('/recipes')
        ->assertOk()
        ->assertSee('25 entries')
        ->assertSeeInOrder(['Post 25', 'Post 02'])
        ->assertDontSee('Post 01')
        ->assertSee('Page 1 of 2')
        ->assertSee('href="'.url('/recipes?page=2').'" rel="next"', false)
        ->assertDontSee('rel="prev"', false);

    $this->get('/recipes?page=2')
        ->assertOk()
        ->assertSee('Post 01')
        ->assertDontSee('Post 02')
        ->assertSee('Page 2 of 2')
        ->assertSee('rel="prev"', false)
        ->assertDontSee('rel="next"', false)
        ->assertSee('<title>Recipes – page 2', false)
        ->assertSee('<link rel="canonical" href="'.url('/recipes?page=2').'">', false);
});

test('pages past the last one are not found', function () {
    manyPosts($this->template);

    $this->get('/recipes?page=3')->assertNotFound();
});

test('an empty template or a single page has no page links', function () {
    $this->get('/recipes')->assertOk()->assertDontSee('aria-label="Pages"', false);

    manyPosts($this->template, 3);

    $this->get('/recipes')->assertOk()->assertDontSee('aria-label="Pages"', false)->assertSee('3 entries');
});

test('pinned posts come first, on the first page', function () {
    $pinned = Post::factory()->published()->for($this->template)->create(['title' => 'Pinned old one', 'published_at' => now()->subYear(), 'pinned_at' => now()]);
    manyPosts($this->template);

    $this->get('/recipes')->assertSeeInOrder(['Pinned old one', 'Post 25']);
    $this->get('/recipes?page=2')->assertDontSee($pinned->title);
});

test('tag pages are paged the same way', function () {
    $tag = Tag::factory()->create(['name' => 'Bread', 'slug' => 'bread']);
    manyPosts($this->template);
    Post::query()->each(fn (Post $post) => $post->tags()->attach($tag));

    $this->get('/tags/bread')->assertOk()->assertSee('25 entries')->assertSee('Page 1 of 2')->assertDontSee('Post 01');
    $this->get('/tags/bread?page=2')->assertOk()->assertSee('Post 01')->assertSee('<title>#Bread – page 2', false);
    $this->get('/tags/bread?page=3')->assertNotFound();
});

test('search results use the same page links', function () {
    manyPosts($this->template);

    $this->get('/search?q=post')
        ->assertOk()
        ->assertSee('Page 1 of 2')
        ->assertSee('href="'.url('/search?q=post&amp;page=2').'" rel="next"', false);
});
