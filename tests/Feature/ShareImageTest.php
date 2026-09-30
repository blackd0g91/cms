<?php

use App\Cms\Settings;
use App\Cms\ShareImage;
use App\Models\Media;
use App\Models\Post;
use App\Models\Template;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    $this->template = Template::factory()->create(['name' => 'Recipes', 'handle' => 'recipes', 'fields' => [], 'layout' => '']);
    $this->post = Post::factory()->published()->for($this->template)->create(['title' => 'Soup', 'slug' => 'soup']);
});

function shareImageUrl(Post $post): string
{
    return app(ShareImage::class)->url($post->fresh());
}

function shareImages(): array
{
    return Storage::disk('local')->files('share-images');
}

test('posts without a thumbnail get the drawn picture in their link previews', function () {
    $this->get('/recipes/soup')
        ->assertOk()
        ->assertSee('<meta property="og:image" content="'.e(shareImageUrl($this->post)).'">', false)
        ->assertSee('<meta property="og:image:width" content="1200">', false)
        ->assertSee('<meta property="og:image:height" content="630">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false);
});

test('posts with an svg thumbnail get the drawn picture too, since previews can not show svgs', function () {
    $svg = Media::factory()->create(['mime_type' => 'image/svg+xml', 'path' => 'media/logo.svg']);
    $this->post->update(['thumbnail_id' => $svg->id]);

    $this->get('/recipes/soup')
        ->assertSee('<meta property="og:image" content="'.e(shareImageUrl($this->post)).'">', false)
        ->assertDontSee('logo.svg">', false);
});

test('posts with a thumbnail keep it, with its size', function () {
    $media = Media::factory()->create(['width' => 800, 'height' => 600]);
    $this->post->update(['thumbnail_id' => $media->id]);

    $this->get('/recipes/soup')
        ->assertSee('<meta property="og:image" content="'.$media->url.'">', false)
        ->assertSee('<meta property="og:image:width" content="800">', false)
        ->assertDontSee('share.png', false);
});

test('the picture is a png the size link previews expect, kept for good at its address', function () {
    $response = $this->get(shareImageUrl($this->post))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png');

    expect($response->headers->get('Cache-Control'))->toContain('immutable')
        ->and(getimagesize($response->baseResponse->getFile()->getPathname()))
        ->toMatchArray([0 => 1200, 1 => 630, 'mime' => 'image/png']);
});

test('the picture is drawn once, and again only when what it shows changes', function () {
    $this->get(shareImageUrl($this->post))->assertOk();
    $first = shareImages();

    $this->get(shareImageUrl($this->post))->assertOk();
    expect(shareImages())->toBe($first)->toHaveCount(1);

    $this->post->update(['title' => 'Better soup']);
    $this->get(shareImageUrl($this->post))->assertOk();

    // The old version is deleted.
    expect(shareImages())->toHaveCount(1)->not->toBe($first);
});

test('the address changes with anything drawn on the picture', function () {
    $versions = [shareImageUrl($this->post)];

    $this->post->update(['title' => 'Soup!']);
    $versions[] = shareImageUrl($this->post);

    $this->template->update(['name' => 'Cooking']);
    $versions[] = shareImageUrl($this->post);

    $this->template->update(['color' => '#2f7d4a']);
    $versions[] = shareImageUrl($this->post);

    app(Settings::class)->update(['site_name' => 'My Notebook']);
    $versions[] = shareImageUrl($this->post);

    app(Settings::class)->update(['logo_background' => ['type' => 'solid', 'from' => '#1e293b', 'to' => null, 'angle' => 0]]);
    $versions[] = shareImageUrl($this->post);

    expect(array_unique($versions))->toHaveCount(6);
});

test('an outdated address still gets the current picture, but not to keep', function () {
    $this->get('/recipes/soup/share.png?v=outdated')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'no-cache, public');
});

test('the pictures of drafts are only shown to logged in users', function () {
    Post::factory()->for($this->template)->create(['slug' => 'draft']);

    $this->get('/recipes/draft/share.png')->assertNotFound();

    $this->actingAs(User::factory()->create())
        ->get('/recipes/draft/share.png')
        ->assertOk()
        ->assertHeader('Cache-Control', 'no-cache, private');
});

test('deleting a post deletes its pictures', function () {
    $this->get(shareImageUrl($this->post))->assertOk();
    expect(shareImages())->toHaveCount(1);

    $this->post->delete();

    expect(shareImages())->toBeEmpty();
});

test('long titles, emoji and a gradient logo background are drawn without errors', function () {
    app(Settings::class)->update(['logo_background' => ['type' => 'gradient', 'from' => '#7c3aed', 'to' => '#db2777', 'angle' => 135]]);
    $this->post->update(['title' => str_repeat('Pão de queijo com requeijão 🧀 ', 12).'Supercalifragilisticexpialidocious-antidisestablishmentarianism']);

    $png = app(ShareImage::class)->render($this->post->fresh());

    expect(getimagesizefromstring($png))->toMatchArray([0 => 1200, 1 => 630]);
});
