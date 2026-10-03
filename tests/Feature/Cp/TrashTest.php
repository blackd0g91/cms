<?php

use App\Cms\MediaUsage;
use App\Cms\Trash;
use App\Models\Media;
use App\Models\Post;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    $this->actingAs(User::factory()->create());

    $this->template = Template::factory()->create(['name' => 'Recipes', 'handle' => 'recipes', 'fields' => [
        ['handle' => 'method', 'label' => 'Method', 'type' => 'markdown', 'required' => false, 'options' => []],
    ], 'layout' => '{{ method }}']);
});

function trashedMedia(): Media
{
    $media = Media::factory()->create();
    Storage::disk('public')->put($media->path, 'image');
    $media->delete();

    return $media;
}

test('guests can not open the trash', function () {
    auth()->logout();

    $this->get(route('cp.trash.index'))->assertRedirect(route('cp.login'));
    $this->delete(route('cp.trash.empty'))->assertRedirect(route('cp.login'));
});

test('the trash lists deleted posts and images, newest first, with the days they have left', function () {
    $older = Post::factory()->for($this->template)->create(['title' => 'Old soup']);
    $this->travel(-10)->days();
    $older->delete();
    $this->travelBack();

    $newer = Post::factory()->for($this->template)->create(['title' => 'New soup']);
    $newer->delete();
    Post::factory()->for($this->template)->create(['title' => 'Still here']);
    $media = trashedMedia();
    Media::factory()->create();

    $this->get(route('cp.trash.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('cp/Trash')
            ->where('days', 30)
            ->has('posts', 2)
            ->where('posts.0.title', 'New soup')
            ->where('posts.0.template', 'Recipes')
            ->where('posts.0.days_left', 30)
            ->where('posts.1.title', 'Old soup')
            ->where('posts.1.days_left', 20)
            ->has('media', 1)
            ->where('media.0.id', $media->id));
});

test('editors can use the trash too', function () {
    $this->actingAs(User::factory()->editor()->create());

    $this->get(route('cp.trash.index'))->assertOk();
});

test('a post in the trash is gone from the site until it is restored', function () {
    $post = Post::factory()->published()->for($this->template)->create(['slug' => 'soup']);

    $this->delete(route('cp.templates.posts.destroy', [$this->template, $post]));

    $this->get($post->url())->assertNotFound();
    $this->get(route('cp.templates.posts.edit', [$this->template, $post]))->assertNotFound();

    $this->post(route('cp.trash.posts.restore', $post->id))->assertRedirect();

    $this->assertNotSoftDeleted($post);
    $this->get($post->url())->assertOk();
});

test('a post can be deleted for good from the trash', function () {
    $post = Post::factory()->for($this->template)->create();
    $post->delete();

    $this->delete(route('cp.trash.posts.destroy', $post->id))->assertRedirect();

    $this->assertModelMissing($post);
});

test('an image in the trash keeps its file until it is deleted for good', function () {
    $media = trashedMedia();

    $this->post(route('cp.trash.media.restore', $media->id));
    $this->assertNotSoftDeleted($media);

    $media->delete();
    $this->delete(route('cp.trash.media.destroy', $media->id));

    $this->assertModelMissing($media);
    Storage::disk('public')->assertMissing($media->path);
});

test('only what is in the trash can be restored or deleted from it', function () {
    $post = Post::factory()->for($this->template)->create();
    $media = Media::factory()->create();

    $this->post(route('cp.trash.posts.restore', $post->id))->assertNotFound();
    $this->delete(route('cp.trash.posts.destroy', $post->id))->assertNotFound();
    $this->post(route('cp.trash.media.restore', $media->id))->assertNotFound();
    $this->delete(route('cp.trash.media.destroy', $media->id))->assertNotFound();

    $this->assertModelExists($post);
    $this->assertModelExists($media);
});

test('emptying the trash deletes everything in it for good', function () {
    $trashed = Post::factory()->for($this->template)->create();
    $trashed->delete();
    $kept = Post::factory()->for($this->template)->create();
    $media = trashedMedia();

    $this->delete(route('cp.trash.empty'))->assertRedirect();

    $this->assertModelMissing($trashed);
    $this->assertModelMissing($media);
    $this->assertModelExists($kept);
    Storage::disk('public')->assertMissing($media->path);
});

test('after '.Trash::DAYS.' days things in the trash are deleted for good', function (string $page) {
    $old = Post::factory()->for($this->template)->create();
    $recent = Post::factory()->for($this->template)->create();
    $media = Media::factory()->create();

    $this->travel(-31)->days();
    $old->delete();
    $media->delete();
    $this->travelBack();
    $recent->delete();

    $this->get(route($page))->assertOk();

    $this->assertModelMissing($old);
    $this->assertModelMissing($media);
    $this->assertSoftDeleted($recent);
})->with(['cp.trash.index', 'cp.dashboard']);

test('a post in the trash keeps its slug', function () {
    $post = Post::factory()->for($this->template)->create(['slug' => 'soup']);
    $post->delete();

    $this->post(route('cp.templates.posts.store', $this->template), ['title' => 'Soup', 'slug' => 'soup', 'status' => 'draft'])
        ->assertSessionHasErrors(['slug' => 'A post in the trash uses this slug. Restore it, or delete it for good from the trash.']);

    $other = Post::factory()->for($this->template)->create(['title' => 'Other soup', 'slug' => 'soup-2']);
    $this->post(route('cp.templates.posts.duplicate', [$this->template, $other]));

    expect(Post::query()->latest('id')->first()->slug)->toBe('soup-2-copy');

    $post->forceDelete();

    $this->post(route('cp.templates.posts.store', $this->template), ['title' => 'Soup', 'slug' => 'soup', 'status' => 'draft'])
        ->assertSessionHasNoErrors();
});

test('a template with posts in the trash can not be deleted', function () {
    Post::factory()->for($this->template)->create()->delete();

    $this->get(route('cp.templates.edit', $this->template))
        ->assertInertia(fn (Assert $page) => $page->where('postsCount', 1)->where('trashedPostsCount', 1));

    $this->delete(route('cp.templates.destroy', $this->template))
        ->assertSessionHasErrors(['template' => 'This template\'s posts are in the trash. Delete them for good from the trash first.']);

    $this->assertModelExists($this->template);
});

test('images used by posts in the trash are still in use, and keep it when restored', function () {
    $media = Media::factory()->create();
    $post = Post::factory()->for($this->template)->create(['title' => 'Soup', 'data' => ['method' => "![]({$media->url})"]]);
    $post->delete();

    expect(app(MediaUsage::class)->for($media))
        ->toBe([['label' => 'Soup (Recipes, in the trash)', 'post_id' => null, 'template_id' => null]]);

    // An image in the trash still counts as used by the post that shows it.
    $media->delete();
    $post->restore();
    $media->restore();

    expect(app(MediaUsage::class)->for($media))
        ->toBe([['label' => 'Soup (Recipes)', 'post_id' => $post->id, 'template_id' => $this->template->id]]);
});

test('posts in the trash are left out of the popular posts', function () {
    $post = Post::factory()->published()->for($this->template)->create();
    $this->withHeader('User-Agent', 'Mozilla/5.0')->get($post->url());
    $post->delete();

    $this->get(route('cp.dashboard'))
        ->assertInertia(fn (Assert $page) => $page->has('popular', 0));
});
