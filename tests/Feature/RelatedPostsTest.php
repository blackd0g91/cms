<?php

use App\Models\Post;
use App\Models\Tag;
use App\Models\Template;

beforeEach(function () {
    $this->recipes = Template::factory()->create(['handle' => 'recipes', 'fields' => []]);
    $this->notes = Template::factory()->create(['handle' => 'notes', 'fields' => []]);
    [$this->vegan, $this->quick, $this->spicy] = Tag::factory()->count(3)->create()->all();

    $this->post = Post::factory()->published()->for($this->recipes)->create(['title' => 'Curry', 'slug' => 'curry']);
    $this->post->tags()->attach([$this->vegan->id, $this->quick->id]);
});

function publishedPost(Template $template, string $title, array $tags = [], string $ago = '1 day'): Post
{
    $post = Post::factory()->published()->for($template)->create(['title' => $title, 'published_at' => now()->sub($ago)]);
    $post->tags()->attach(array_map(fn (Tag $tag) => $tag->id, $tags));

    return $post;
}

test('posts sharing the most tags come first, then the same template', function () {
    publishedPost($this->recipes, 'Same template only', ago: '1 hour');
    publishedPost($this->notes, 'One shared tag', [$this->vegan], '2 days');
    publishedPost($this->notes, 'Two shared tags', [$this->vegan, $this->quick], '1 year');
    publishedPost($this->notes, 'Unrelated', [$this->spicy]);

    expect($this->post->related()->pluck('title')->all())->toBe(['Two shared tags', 'One shared tag', 'Same template only']);
});

test('with equal tags, the same template and then newer posts win', function () {
    publishedPost($this->notes, 'Other template', [$this->vegan], '1 hour');
    publishedPost($this->recipes, 'Same template, older', [$this->vegan], '1 year');
    publishedPost($this->recipes, 'Same template, newer', [$this->vegan], '1 week');

    expect($this->post->related()->pluck('title')->all())->toBe(['Same template, newer', 'Same template, older', 'Other template']);
});

test('drafts, the post itself and unrelated posts are left out', function () {
    Post::factory()->for($this->recipes)->create(['title' => 'A draft']);
    publishedPost($this->notes, 'Unrelated', [$this->spicy]);

    expect($this->post->related())->toBeEmpty();
});

test('untagged posts get suggestions from their template', function () {
    $untagged = publishedPost($this->recipes, 'Untagged');
    publishedPost($this->notes, 'Elsewhere', [$this->vegan]);

    expect($untagged->related()->pluck('title')->all())->toBe(['Curry']);
});

test('the post page shows related posts as cards, and nothing when there are none', function () {
    $this->get('/recipes/curry')->assertOk()->assertDontSee('Keep reading');

    publishedPost($this->notes, 'Salad', [$this->vegan])->update(['slug' => 'salad']);

    $this->get('/recipes/curry')
        ->assertSee('Keep reading')
        ->assertSee('Salad')
        ->assertSee('href="'.url('/notes/salad').'"', false);
});
