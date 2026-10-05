<?php

use App\Models\Post;
use App\Models\Template;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());

    $this->template = Template::factory()->create([
        'handle' => 'cheatsheets',
        'fields' => [['handle' => 'body', 'label' => 'Body', 'type' => 'markdown', 'required' => false, 'options' => []]],
        'layout' => '{{ body }}',
    ]);
});

function editPost(Post $post, array $overrides = []): void
{
    test()->put(route('cp.templates.posts.update', [$post->template, $post]), [
        'title' => $post->title,
        'slug' => $post->slug,
        'status' => $post->status->value,
        'data' => $post->data,
        ...$overrides,
    ])->assertSessionHasNoErrors();
}

test('posts edited after the day they were published say when they were updated', function () {
    $this->travelTo('2026-10-01 12:00');
    $post = Post::factory()->published()->for($this->template)->create(['slug' => 'git', 'data' => ['body' => 'git add']]);

    $this->travelTo('2026-10-05 09:30');
    editPost($post, ['data' => ['body' => 'git add -p']]);

    expect($post->fresh()->content_updated_at->toDateTimeString())->toBe('2026-10-05 09:30:00');

    $this->get('/cheatsheets/git')
        ->assertOk()
        ->assertSeeInOrder(['October 1, 2026', 'Updated', 'October 5, 2026'])
        ->assertSee('<time datetime="'.now()->toIso8601String().'">October 5, 2026</time>', false);
});

test('a new title counts as an update too', function () {
    $post = Post::factory()->published()->for($this->template)->create(['published_at' => now()->subWeek()]);

    editPost($post, ['title' => 'A better title']);

    expect($post->fresh()->content_updated_at)->not->toBeNull();
});

test('edits on the day a post was published are not shown as updates', function () {
    $this->travelTo('2026-10-05 08:00');
    $post = Post::factory()->published()->for($this->template)->create(['slug' => 'git', 'data' => ['body' => 'git add']]);

    $this->travelTo('2026-10-05 18:00');
    editPost($post, ['data' => ['body' => 'git add -p']]);

    $this->get('/cheatsheets/git')->assertOk()->assertDontSee('Updated');
});

test('changes readers do not see as content leave the updated date alone', function () {
    $post = Post::factory()->published()->for($this->template)->create(['slug' => 'git', 'published_at' => now()->subWeek(), 'data' => ['body' => 'git add']]);

    editPost($post, ['summary' => 'Staging changes.', 'pinned' => true, 'tags' => ['git']]);

    expect($post->fresh()->content_updated_at)->toBeNull();
    $this->get('/cheatsheets/git')->assertDontSee('Updated');
});

test('edits made before a post was first published are part of the original', function () {
    $post = Post::factory()->for($this->template)->create(['data' => ['body' => 'draft']]);

    editPost($post, ['status' => 'published', 'data' => ['body' => 'final']]);

    expect($post->fresh()->content_updated_at)->toBeNull();
});

test('edits to an unpublished post that was published before still count', function () {
    $post = Post::factory()->for($this->template)->create(['published_at' => now()->subWeek(), 'data' => ['body' => 'old']]);

    editPost($post, ['data' => ['body' => 'new']]);

    expect($post->fresh()->content_updated_at)->not->toBeNull();
});

test('copies of a post start without an updated date', function () {
    $post = Post::factory()->published()->for($this->template)->create(['content_updated_at' => now()]);

    $this->post(route('cp.templates.posts.duplicate', [$this->template, $post]));

    expect(Post::latest('id')->first()->content_updated_at)->toBeNull();
});

test('the migration finds when existing posts were last edited after publishing, from their history', function () {
    $edited = Post::factory()->published()->for($this->template)->create(['published_at' => '2026-10-01 10:00:00']);
    $edited->revisions()->createMany([
        ['title' => 'Git', 'slug' => 'git', 'status' => 'published', 'data' => ['body' => 'v1'], 'created_at' => '2026-10-01 10:00:00'],
        ['title' => 'Git', 'slug' => 'git', 'status' => 'published', 'data' => ['body' => 'v2'], 'created_at' => '2026-10-03 10:00:00'],
        // Only the slug changed, which is not content.
        ['title' => 'Git', 'slug' => 'git-basics', 'status' => 'published', 'data' => ['body' => 'v2'], 'created_at' => '2026-10-04 10:00:00'],
    ]);

    $editedBeforePublishing = Post::factory()->published()->for($this->template)->create(['published_at' => '2026-10-02 10:00:00']);
    $editedBeforePublishing->revisions()->createMany([
        ['title' => 'Draft', 'slug' => 'draft', 'status' => 'draft', 'data' => ['body' => 'v1'], 'created_at' => '2026-10-01 10:00:00'],
        ['title' => 'Final', 'slug' => 'draft', 'status' => 'published', 'data' => ['body' => 'v1'], 'created_at' => '2026-10-02 10:00:00'],
    ]);

    $migration = require database_path('migrations/2026_10_05_000001_add_content_updated_at_to_posts_table.php');
    $migration->down();
    $migration->up();

    expect($edited->fresh()->content_updated_at->toDateTimeString())->toBe('2026-10-03 10:00:00')
        ->and($editedBeforePublishing->fresh()->content_updated_at)->toBeNull();
});
