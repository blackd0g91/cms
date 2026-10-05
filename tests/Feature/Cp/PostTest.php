<?php

use App\Enums\PostStatus;
use App\Models\Media;
use App\Models\Post;
use App\Models\Template;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->template = Template::factory()->create([
        'fields' => [
            ['handle' => 'servings', 'label' => 'Servings', 'type' => 'number', 'required' => true, 'options' => []],
            ['handle' => 'vegan', 'label' => 'Vegan', 'type' => 'boolean', 'required' => false, 'options' => []],
            ['handle' => 'ingredients', 'label' => 'Ingredients', 'type' => 'list', 'required' => false, 'options' => []],
            ['handle' => 'difficulty', 'label' => 'Difficulty', 'type' => 'select', 'required' => false, 'options' => ['Easy', 'Hard']],
        ],
    ]);
});

test('all posts are listed with their template', function () {
    $post = Post::factory()->for($this->template)->create(['updated_at' => now()]);
    $other = Post::factory()->published()->create(['updated_at' => now()->subDay()]);

    $this->get(route('cp.posts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('cp/posts/Index')
            ->has('posts.data', 2)
            ->where('posts.data.0.id', $post->id)
            ->where('posts.data.0.template.name', $this->template->name)
            ->where('posts.data.1.id', $other->id)
            ->where('filters', ['template' => null, 'status' => null]));
});

test('posts can be filtered by template and status', function () {
    $draft = Post::factory()->for($this->template)->create();
    Post::factory()->published()->for($this->template)->create();
    Post::factory()->create();

    $this->get(route('cp.posts.index', ['template' => $this->template->id]))
        ->assertInertia(fn (Assert $page) => $page->has('posts.data', 2));

    $this->get(route('cp.posts.index', ['template' => $this->template->id, 'status' => 'draft']))
        ->assertInertia(fn (Assert $page) => $page
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $draft->id)
            ->where('filters', ['template' => $this->template->id, 'status' => 'draft']));
});

test('new posts start by choosing a template', function () {
    Template::factory()->create();

    $this->get(route('cp.posts.create'))
        ->assertInertia(fn (Assert $page) => $page->component('cp/posts/Choose')->has('templates', 2));
});

test('with a single template the choice is skipped', function () {
    Template::query()->delete();
    $template = Template::factory()->create();

    $this->get(route('cp.posts.create'))->assertRedirect(route('cp.templates.posts.create', $template));
});

test('a post can be created', function () {
    $this->post(route('cp.templates.posts.store', $this->template), [
        'title' => 'Pão de Queijo',
        'slug' => '',
        'status' => 'draft',
        'data' => [
            'servings' => '4',
            'vegan' => false,
            'ingredients' => ['Cheese', '', 'Tapioca flour'],
            'unknown' => 'dropped',
        ],
    ])->assertRedirect();

    $post = Post::sole();

    expect($post->slug)->toBe('pao-de-queijo')
        ->and($post->status)->toBe(PostStatus::Draft)
        ->and($post->published_at)->toBeNull()
        ->and($post->data)->toBe([
            'servings' => 4,
            'vegan' => false,
            'ingredients' => ['Cheese', 'Tapioca flour'],
            'difficulty' => null,
        ]);
});

test('publishing a post records when it was published', function () {
    $post = Post::factory()->for($this->template)->create(['data' => ['servings' => 2]]);

    $this->put(route('cp.templates.posts.update', [$this->template, $post]), [
        'title' => $post->title,
        'slug' => 'a-custom-slug',
        'status' => 'published',
        'data' => ['servings' => 2],
    ])->assertRedirect(route('cp.templates.posts.edit', [$this->template, $post]));

    $post->refresh();

    expect($post->slug)->toBe('a-custom-slug')
        ->and($post->isPublished())->toBeTrue()
        ->and($post->published_at)->not->toBeNull();
});

test('post fields are validated against the template', function (array $data, string $error) {
    $this->post(route('cp.templates.posts.store', $this->template), [
        'title' => 'Soup',
        'status' => 'draft',
        'data' => ['servings' => 2, ...$data],
    ])->assertSessionHasErrors($error);
})->with([
    'missing required field' => [['servings' => null], 'data.servings'],
    'not a number' => [['servings' => 'many'], 'data.servings'],
    'option not allowed' => [['difficulty' => 'Medium'], 'data.difficulty'],
]);

test('slugs are unique within a template', function () {
    Post::factory()->for($this->template)->create(['slug' => 'soup']);
    Post::factory()->create(['slug' => 'elsewhere']);

    $payload = ['title' => 'Soup', 'status' => 'draft', 'data' => ['servings' => 1]];

    $this->post(route('cp.templates.posts.store', $this->template), [...$payload, 'slug' => 'soup'])
        ->assertSessionHasErrors('slug');

    $this->post(route('cp.templates.posts.store', $this->template), [...$payload, 'slug' => 'elsewhere'])
        ->assertSessionHasNoErrors();
});

test('posts are scoped to their template', function () {
    $post = Post::factory()->create();

    $this->get(route('cp.templates.posts.edit', [$this->template, $post]))->assertNotFound();
});

test('a deleted post goes to the trash', function () {
    $post = Post::factory()->for($this->template)->create();

    $this->delete(route('cp.templates.posts.destroy', [$this->template, $post]))
        ->assertRedirect(route('cp.posts.index'));

    $this->assertSoftDeleted($post);
});

test('markdown can be previewed', function () {
    $this->postJson(route('cp.markdown.preview'), [
        'markdown' => "# Hello\n\n```php\necho 'hi';\n```",
    ])
        ->assertOk()
        ->assertJsonPath('html', fn (string $html) => str_contains($html, '<h1>Hello</h1>')
            && str_contains($html, 'class="phiki language-php'));
});

test('guests can not preview markdown', function () {
    auth()->logout();

    $this->postJson(route('cp.markdown.preview'), ['markdown' => 'hi'])->assertUnauthorized();
});

test('image fields store a media id and must point to existing media', function () {
    $template = Template::factory()->create([
        'fields' => [['handle' => 'photo', 'label' => 'Photo', 'type' => 'image', 'required' => false, 'options' => []]],
    ]);
    $media = Media::factory()->create();
    $payload = ['title' => 'Soup', 'status' => 'draft'];

    $this->post(route('cp.templates.posts.store', $template), [...$payload, 'data' => ['photo' => 999]])
        ->assertSessionHasErrors('data.photo');

    $this->post(route('cp.templates.posts.store', $template), [...$payload, 'data' => ['photo' => $media->id]])
        ->assertSessionHasNoErrors();

    $post = Post::sole();
    expect($post->data['photo'])->toBe($media->id);

    $this->get(route('cp.templates.posts.edit', [$template, $post]))
        ->assertInertia(fn ($page) => $page->where("media.{$media->id}.url", $media->url));
});

test('a post can be duplicated into a draft', function () {
    $post = Post::factory()->published()->for($this->template)->create([
        'title' => 'Soup',
        'slug' => 'soup',
        'data' => ['servings' => 4, 'ingredients' => ['Water']],
    ]);

    $this->post(route('cp.templates.posts.duplicate', [$this->template, $post]))
        ->assertRedirect(route('cp.templates.posts.edit', [$this->template, Post::latest('id')->first()]));

    $copy = Post::latest('id')->first();

    expect($copy->id)->not->toBe($post->id)
        ->and($copy->title)->toBe('Soup (copy)')
        ->and($copy->slug)->toBe('soup-copy')
        ->and($copy->isPublished())->toBeFalse()
        ->and($copy->published_at)->toBeNull()
        ->and($copy->data)->toBe($post->data)
        ->and($copy->search_index)->toContain('soup (copy)');
});

test('duplicate slugs get a number', function () {
    $post = Post::factory()->for($this->template)->create(['slug' => 'soup']);
    Post::factory()->for($this->template)->create(['slug' => 'soup-copy']);

    $this->post(route('cp.templates.posts.duplicate', [$this->template, $post]));

    expect(Post::latest('id')->first()->slug)->toBe('soup-copy-2');
});

test('posts can only be duplicated through their own template', function () {
    $post = Post::factory()->create();

    $this->post(route('cp.templates.posts.duplicate', [$this->template, $post]))->assertNotFound();
});

test('a post can have a thumbnail', function () {
    $media = Media::factory()->create();
    $payload = ['title' => 'Soup', 'status' => 'draft', 'data' => ['servings' => 2]];

    $this->post(route('cp.templates.posts.store', $this->template), [...$payload, 'thumbnail_id' => 999])
        ->assertSessionHasErrors('thumbnail_id');

    $this->post(route('cp.templates.posts.store', $this->template), [...$payload, 'thumbnail_id' => $media->id])
        ->assertSessionHasNoErrors();

    $post = Post::sole();
    expect($post->thumbnail->is($media))->toBeTrue();

    $this->get(route('cp.templates.posts.edit', [$this->template, $post]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('post.thumbnail_id', $media->id)
            ->where("media.{$media->id}.id", $media->id));
});

test('a thumbnail in the trash is hidden, and cleared once deleted for good', function () {
    $media = Media::factory()->create();
    $post = Post::factory()->for($this->template)->create(['thumbnail_id' => $media->id]);

    $this->delete(route('cp.media.destroy', ['media' => $media, 'force' => 1]))->assertSessionHasNoErrors();

    // Kept, for when the image is restored.
    expect($post->fresh()->thumbnail_id)->toBe($media->id)
        ->and($post->fresh()->thumbnail)->toBeNull();

    $this->delete(route('cp.trash.media.destroy', $media->id));

    expect($post->fresh()->thumbnail_id)->toBeNull();
});

test('thumbnails count as media usage', function () {
    $media = Media::factory()->create();
    Post::factory()->for($this->template)->create(['thumbnail_id' => $media->id]);

    $this->delete(route('cp.media.destroy', $media))->assertSessionHasErrors('media');
});

test('a post can have a short summary, tidied up when saved', function () {
    $this->post(route('cp.templates.posts.store', $this->template), [
        'title' => 'Soup',
        'summary' => "  A quick soup\n for   cold nights.  ",
        'status' => 'draft',
        'data' => ['servings' => 2],
    ])->assertRedirect();

    expect(Post::sole()->summary)->toBe('A quick soup for cold nights.');

    $post = Post::sole();
    $this->put(route('cp.templates.posts.update', [$this->template, $post]), [
        'title' => 'Soup',
        'slug' => $post->slug,
        'summary' => '',
        'status' => 'draft',
        'data' => ['servings' => 2],
    ])->assertRedirect();

    expect($post->refresh()->summary)->toBeNull();

    $this->put(route('cp.templates.posts.update', [$this->template, $post]), [
        'title' => 'Soup',
        'slug' => $post->slug,
        'summary' => str_repeat('a', 161),
        'status' => 'draft',
        'data' => ['servings' => 2],
    ])->assertSessionHasErrors('summary');
});
