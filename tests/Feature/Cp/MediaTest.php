<?php

use App\Cms\Settings;
use App\Models\Media;
use App\Models\Post;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->create());
});

function fakePng(string $name = 'photo.png'): UploadedFile
{
    // A 1x1 PNG, so tests do not depend on the GD extension.
    return UploadedFile::fake()->createWithContent(
        $name,
        base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='),
    );
}

test('guests can not access media', function () {
    auth()->logout();

    $this->get(route('cp.media.index'))->assertRedirect(route('cp.login'));
    $this->post(route('cp.media.store'), ['file' => fakePng()])->assertRedirect(route('cp.login'));
});

test('the media library is listed', function () {
    $media = Media::factory()->create();

    $this->get(route('cp.media.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('cp/media/Index')
            ->where('media.0.id', $media->id)
            ->where('media.0.url', Storage::disk('public')->url($media->path)));

    $this->getJson(route('cp.media.library'))->assertJsonPath('media.0.id', $media->id);
});

test('an image can be uploaded', function () {
    $this->post(route('cp.media.store'), ['file' => fakePng()])->assertRedirect();

    $media = Media::sole();

    Storage::disk('public')->assertExists($media->path);

    expect($media->filename)->toBe('photo.png')
        ->and($media->mime_type)->toBe('image/png')
        ->and($media->width)->toBe(1)
        ->and($media->height)->toBe(1);
});

test('uploads from the image picker get json back', function () {
    $this->postJson(route('cp.media.store'), ['file' => fakePng()])
        ->assertCreated()
        ->assertJsonPath('media.id', Media::sole()->id);
});

test('only images can be uploaded', function (UploadedFile $file) {
    $this->post(route('cp.media.store'), ['file' => $file])->assertSessionHasErrors('file');

    expect(Media::count())->toBe(0);
})->with([
    'pdf' => fn () => UploadedFile::fake()->create('file.pdf', 10, 'application/pdf'),
    'text file named svg' => fn () => UploadedFile::fake()->createWithContent('logo.svg', 'just some text'),
    'too large' => fn () => UploadedFile::fake()->create('huge.png', 20 * 1024, 'image/png'),
]);

test('alt text can be updated', function () {
    $media = Media::factory()->create();

    $this->patch(route('cp.media.update', $media), ['alt' => 'A bowl of soup'])->assertRedirect();

    expect($media->fresh()->alt)->toBe('A bowl of soup');
});

test('deleting media moves it to the trash, keeping the file', function () {
    $this->post(route('cp.media.store'), ['file' => fakePng()]);
    $media = Media::sole();

    $this->delete(route('cp.media.destroy', $media))->assertRedirect();

    $this->assertSoftDeleted($media);
    Storage::disk('public')->assertExists($media->path);
});

function imageTemplate(): Template
{
    return Template::factory()->create([
        'name' => 'Recipes',
        'fields' => [
            ['handle' => 'photo', 'label' => 'Photo', 'type' => 'image', 'required' => false, 'options' => []],
            ['handle' => 'method', 'label' => 'Method', 'type' => 'markdown', 'required' => false, 'options' => []],
        ],
    ]);
}

test('media usage is listed for image fields, markdown and the home intro', function () {
    $inField = Media::factory()->create();
    $inMarkdown = Media::factory()->create();
    $inIntro = Media::factory()->create();
    $unused = Media::factory()->create();

    $template = imageTemplate();
    $post = Post::factory()->for($template)->create([
        'title' => 'Soup',
        'data' => ['photo' => $inField->id, 'method' => "Look:\n\n![soup]({$inMarkdown->url})"],
    ]);
    app(Settings::class)->update(['home_intro' => "![me]({$inIntro->url})"]);

    $this->get(route('cp.media.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where("usages.{$inField->id}", [['label' => 'Soup (Recipes)', 'post_id' => $post->id, 'template_id' => $template->id]])
            ->where("usages.{$inMarkdown->id}.0.post_id", $post->id)
            ->where("usages.{$inIntro->id}", [['label' => 'Home page intro', 'post_id' => null, 'template_id' => null]])
            ->where("usages.{$unused->id}", []));
});

test('images in use are only deleted when forced', function () {
    $media = Media::factory()->create();
    Post::factory()->for(imageTemplate())->create(['title' => 'Soup', 'data' => ['photo' => $media->id]]);

    $this->delete(route('cp.media.destroy', $media))->assertSessionHasErrors(['media' => 'This image is still used in: Soup (Recipes).']);
    $this->assertModelExists($media);

    $this->delete(route('cp.media.destroy', ['media' => $media, 'force' => 1]))->assertSessionHasNoErrors();
    $this->assertSoftDeleted($media);
});

test('a number in another field type does not count as image usage', function () {
    $media = Media::factory()->create();
    $template = Template::factory()->create([
        'fields' => [['handle' => 'servings', 'label' => 'Servings', 'type' => 'number', 'required' => false, 'options' => []]],
    ]);
    Post::factory()->for($template)->create(['data' => ['servings' => $media->id]]);

    $this->delete(route('cp.media.destroy', $media))->assertSessionHasNoErrors();
});

test('svg images are sanitized on upload', function () {
    $svg = <<<'SVG'
        <svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" viewBox="0 0 120 60" onload="alert(1)">
            <script>alert(document.cookie)</script>
            <a xlink:href="javascript:alert(2)"><rect width="10" height="10" fill="red"/></a>
            <image href="https://evil.example/track.png" width="1" height="1"/>
            <foreignObject><iframe src="https://evil.example"></iframe></foreignObject>
            <circle cx="30" cy="30" r="20" fill="#c2410c"/>
        </svg>
        SVG;

    $this->post(route('cp.media.store'), ['file' => UploadedFile::fake()->createWithContent('logo.svg', $svg)])
        ->assertSessionHasNoErrors();

    $media = Media::sole();
    $stored = Storage::disk('public')->get($media->path);

    expect($media->mime_type)->toBe('image/svg+xml')
        ->and($media->path)->toEndWith('.svg')
        ->and($media->width)->toBe(120)
        ->and($media->height)->toBe(60)
        ->and($media->size)->toBe(strlen($stored))
        ->and($stored)->toContain('<circle')
        ->not->toContain('<script')
        ->not->toContain('onload')
        ->not->toContain('javascript:')
        ->not->toContain('evil.example')
        ->not->toContain('foreignObject');
});

test('svg sizes come from width and height when given', function () {
    $svg = '<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg" width="48px" height="32" viewBox="0 0 10 10"><rect width="10" height="10"/></svg>';

    $this->post(route('cp.media.store'), ['file' => UploadedFile::fake()->createWithContent('icon.svg', $svg)]);

    expect(Media::sole())->width->toBe(48)->height->toBe(32);
});

test('broken svg files are rejected', function () {
    $this->post(route('cp.media.store'), [
        'file' => UploadedFile::fake()->createWithContent('broken.svg', '<svg xmlns="http://www.w3.org/2000/svg"><rect'),
    ])->assertSessionHasErrors('file');

    expect(Media::count())->toBe(0);
});

test('image usage is kept up to date as posts change', function () {
    $photo = Media::factory()->create();
    $screenshot = Media::factory()->create();
    $template = imageTemplate();

    $post = Post::factory()->for($template)->create([
        'data' => ['photo' => $photo->id, 'method' => "![shot]({$screenshot->url})"],
    ]);

    expect($post->media()->pluck('id')->sort()->values()->all())->toBe([$photo->id, $screenshot->id]);

    $post->update(['data' => ['photo' => null, 'method' => 'No images now']]);

    expect($post->media()->count())->toBe(0);
});

test('removing an image field from a template updates usage', function () {
    $photo = Media::factory()->create();
    $template = imageTemplate();
    $post = Post::factory()->for($template)->create(['data' => ['photo' => $photo->id]]);

    $this->put(route('cp.templates.update', $template), [
        'name' => $template->name,
        'handle' => $template->handle,
        'fields' => [['original_handle' => 'method', 'handle' => 'method', 'label' => 'Method', 'type' => 'markdown']],
        'layout' => '{{ method }}',
    ])->assertSessionHasNoErrors();

    expect($post->media()->count())->toBe(0);
    $this->delete(route('cp.media.destroy', $photo))->assertSessionHasNoErrors();
});

test('the migration indexes images used by existing posts', function () {
    $photo = Media::factory()->create();
    $post = Post::factory()->for(imageTemplate())->create(['data' => ['photo' => $photo->id]]);

    $migration = require database_path('migrations/2026_09_29_000002_create_media_post_table.php');
    $migration->down();
    $migration->up();

    expect($post->media()->pluck('id')->all())->toBe([$photo->id]);
});

test('the media page does not load every post', function () {
    $template = imageTemplate();
    Post::factory()->count(20)->for($template)->create();
    $used = Media::factory()->create();
    Post::factory()->for($template)->create(['title' => 'Soup', 'data' => ['photo' => $used->id]]);

    DB::enableQueryLog();
    $this->get(route('cp.media.index'))->assertOk();
    $postQueries = collect(DB::getQueryLog())->filter(fn ($query) => str_contains($query['query'], 'from "posts"'));

    expect($postQueries)->toHaveCount(1)
        ->and($postQueries->first()['query'])->toContain('exists');
});
