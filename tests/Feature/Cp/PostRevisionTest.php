<?php

use App\Models\Media;
use App\Models\Post;
use App\Models\PostRevision;
use App\Models\Template;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create(['name' => 'Murilo']);
    $this->actingAs($this->user);

    $this->template = Template::factory()->create([
        'fields' => [
            ['handle' => 'body', 'label' => 'Body', 'type' => 'markdown', 'required' => false, 'options' => []],
            ['handle' => 'photo', 'label' => 'Photo', 'type' => 'image', 'required' => false, 'options' => []],
        ],
    ]);
});

function savePost(Template $template, ?Post $post, array $overrides = []): void
{
    $payload = ['title' => 'Soup', 'slug' => 'soup', 'status' => 'draft', 'data' => ['body' => 'v1'], ...$overrides];

    $post
        ? test()->put(route('cp.templates.posts.update', [$template, $post]), $payload)
        : test()->post(route('cp.templates.posts.store', $template), $payload);
}

test('every save records a version, skipping saves that change nothing', function () {
    savePost($this->template, null);
    $post = Post::sole();

    savePost($this->template, $post, ['data' => ['body' => 'v2']]);
    savePost($this->template, $post, ['data' => ['body' => 'v2']]);
    savePost($this->template, $post, ['status' => 'published', 'data' => ['body' => 'v2']]);

    $revisions = $post->revisions()->get();

    expect($revisions)->toHaveCount(3)
        ->and($revisions->pluck('data.body')->all())->toBe(['v2', 'v2', 'v1'])
        ->and($revisions->first()->status->value)->toBe('published')
        ->and($revisions->first()->user_id)->toBe($this->user->id);
});

test('only the newest versions are kept', function () {
    savePost($this->template, null);
    $post = Post::sole();

    foreach (range(2, Post::KEPT_REVISIONS + 5) as $version) {
        savePost($this->template, $post, ['data' => ['body' => "v{$version}"]]);
    }

    expect($post->revisions()->count())->toBe(Post::KEPT_REVISIONS)
        ->and($post->revisions()->first()->data['body'])->toBe('v'.(Post::KEPT_REVISIONS + 5));
});

test('the editor lists the versions', function () {
    savePost($this->template, null);
    $post = Post::sole();
    savePost($this->template, $post, ['data' => ['body' => 'v2']]);

    $this->get(route('cp.templates.posts.edit', [$this->template, $post]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('revisions', 2)
            ->where('revisions.0.user', 'Murilo')
            ->where('post.updated_at', fn ($value) => $value !== null));
});

test('a version can be loaded with the images it used', function () {
    $media = Media::factory()->create();
    savePost($this->template, null, ['thumbnail_id' => $media->id, 'data' => ['body' => 'old', 'photo' => $media->id]]);
    $post = Post::sole();
    $revision = $post->revisions()->first();

    $this->getJson(route('cp.templates.posts.revisions.show', [$this->template, $post, $revision]))
        ->assertOk()
        ->assertJsonPath('revision.data.body', 'old')
        ->assertJsonPath('revision.thumbnail_id', $media->id)
        ->assertJsonPath("media.{$media->id}.url", $media->url);
});

test('versions are only reachable through their own post', function () {
    savePost($this->template, null);
    $other = Post::factory()->for($this->template)->create();
    $other->recordRevision();
    $post = Post::query()->where('slug', 'soup')->sole();

    $this->getJson(route('cp.templates.posts.revisions.show', [$this->template, $post, $other->revisions()->first()]))
        ->assertNotFound();
});

test('duplicates start their own history', function () {
    savePost($this->template, null);
    $post = Post::sole();

    $this->post(route('cp.templates.posts.duplicate', [$this->template, $post]));

    $copy = Post::latest('id')->first();
    expect($copy->revisions()->count())->toBe(1)
        ->and($copy->revisions()->first()->title)->toBe('Soup (copy)');
});

test('renaming a field also updates old versions', function () {
    savePost($this->template, null, ['data' => ['body' => 'Stir']]);
    $post = Post::sole();

    $this->put(route('cp.templates.update', $this->template), [
        'name' => $this->template->name,
        'handle' => $this->template->handle,
        'fields' => [
            ['original_handle' => 'body', 'handle' => 'method', 'label' => 'Method', 'type' => 'markdown'],
            ['original_handle' => 'photo', 'handle' => 'photo', 'label' => 'Photo', 'type' => 'image'],
        ],
        'layout' => '{{ method }}',
    ])->assertSessionHasNoErrors();

    expect($post->revisions()->first()->data)
        ->toHaveKey('method', 'Stir')
        ->not->toHaveKey('body');
});

test('deleting a post deletes its history', function () {
    savePost($this->template, null);
    $post = Post::sole();

    $this->delete(route('cp.templates.posts.destroy', [$this->template, $post]));

    expect(PostRevision::count())->toBe(0);
});

test('the migration starts a history for existing posts', function () {
    $post = Post::factory()->for($this->template)->create(['title' => 'Old post']);

    $migration = require database_path('migrations/2026_09_29_000001_create_post_revisions_table.php');
    $migration->down();
    $migration->up();

    expect($post->revisions()->sole()->title)->toBe('Old post');
});
