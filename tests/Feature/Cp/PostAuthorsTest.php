<?php

use App\Models\Post;
use App\Models\Template;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->writer = User::factory()->editor()->create(['name' => 'Ana Souza']);
    $this->actingAs($this->writer);

    $this->template = Template::factory()->create(['handle' => 'recipes', 'fields' => [], 'layout' => '<p>{{ title }}</p>']);
});

function writePost(Template $template, array $attributes = []): void
{
    test()->post(route('cp.templates.posts.store', $template), [
        'title' => 'Bread',
        'status' => 'published',
        ...$attributes,
    ])->assertRedirect();
}

test('a new post is by whoever writes it, unless they credit someone else or no one', function () {
    $other = User::factory()->create(['name' => 'Bruno']);

    writePost($this->template);
    writePost($this->template, ['title' => 'Cake', 'author_id' => $other->id]);
    writePost($this->template, ['title' => 'About', 'author_id' => null]);

    expect(Post::query()->pluck('author_id', 'title')->all())
        ->toBe(['Bread' => $this->writer->id, 'Cake' => $other->id, 'About' => null]);

    $this->post(route('cp.templates.posts.store', $this->template), ['title' => 'Soup', 'status' => 'draft', 'author_id' => 999])
        ->assertSessionHasErrors('author_id');
});

test('saving without an author keeps the one the post has', function () {
    $post = Post::factory()->for($this->template)->create(['author_id' => $this->writer->id]);

    $this->put(route('cp.templates.posts.update', [$this->template, $post]), [
        'title' => 'Renamed',
        'slug' => $post->slug,
        'status' => 'draft',
    ])->assertRedirect();

    expect($post->refresh()->author_id)->toBe($this->writer->id);
});

test('the editor offers everyone as an author, and lists show who wrote each post', function () {
    User::factory()->create(['name' => 'Bruno']);
    $post = Post::factory()->for($this->template)->create(['author_id' => $this->writer->id]);

    $this->get(route('cp.templates.posts.edit', [$this->template, $post]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('post.author_id', $this->writer->id)
            ->where('authors', [['id' => $this->writer->id, 'name' => 'Ana Souza'], ['id' => User::query()->where('name', 'Bruno')->value('id'), 'name' => 'Bruno']]));

    $this->get(route('cp.posts.index'))
        ->assertInertia(fn (Assert $page) => $page->where('posts.data.0.author', 'Ana Souza'));
});

test('a copy is by whoever made it', function () {
    $other = User::factory()->create();
    $post = Post::factory()->for($this->template)->create(['author_id' => $other->id]);

    $this->post(route('cp.templates.posts.duplicate', [$this->template, $post]))->assertRedirect();

    expect(Post::query()->latest('id')->first()?->author_id)->toBe($this->writer->id);
});

test('the post page and feeds say who wrote it', function () {
    $post = Post::factory()->published()->for($this->template)->create(['title' => 'Bread', 'author_id' => $this->writer->id]);
    $anonymous = Post::factory()->published()->for($this->template)->create(['title' => 'About', 'author_id' => null]);

    $this->get($post->url())
        ->assertOk()
        ->assertSee('By Ana Souza &middot;', false)
        ->assertSee('<meta name="author" content="Ana Souza">', false);

    $this->get($anonymous->url())->assertOk()->assertDontSee('By ')->assertDontSee('name="author"', false);

    $this->get(route('feed'))
        ->assertOk()
        ->assertSee('<dc:creator>Ana Souza</dc:creator>', false)
        ->assertSee('xmlns:dc="http://purl.org/dc/elements/1.1/"', false);
});
