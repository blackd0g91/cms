<?php

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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
    'svg' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
    'too large' => fn () => UploadedFile::fake()->create('huge.png', 20 * 1024, 'image/png'),
]);

test('alt text can be updated', function () {
    $media = Media::factory()->create();

    $this->patch(route('cp.media.update', $media), ['alt' => 'A bowl of soup'])->assertRedirect();

    expect($media->fresh()->alt)->toBe('A bowl of soup');
});

test('deleting media removes the file', function () {
    $this->post(route('cp.media.store'), ['file' => fakePng()]);
    $media = Media::sole();

    $this->delete(route('cp.media.destroy', $media))->assertRedirect();

    $this->assertModelMissing($media);
    Storage::disk('public')->assertMissing($media->path);
});
