<?php

use App\Models\Media;
use App\Models\Post;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->actingAs(User::factory()->create());
});

/**
 * A real image made with GD. $orientation adds an EXIF rotation tag, the way
 * phone cameras do instead of rotating the pixels.
 */
function realImage(int $width, int $height, string $type = 'png', ?int $orientation = null): UploadedFile
{
    $image = imagecreatetruecolor($width, $height);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 200, 60, 20, 0));
    // A transparent top-left corner, to check transparency survives.
    imagefilledrectangle($image, 0, 0, 20, 20, imagecolorallocatealpha($image, 0, 0, 0, 127));

    ob_start();
    match ($type) {
        'png' => imagepng($image),
        'jpg' => imagejpeg($image, null, 90),
        'gif' => imagegif($image),
    };
    $bytes = (string) ob_get_clean();

    if ($orientation !== null) {
        // Minimal EXIF block: a TIFF header with one IFD entry, Orientation (0x0112).
        $tiff = 'II*'."\0".pack('V', 8).pack('v', 1).pack('vvVvv', 0x0112, 3, 1, $orientation, 0).pack('V', 0);
        $app1 = "\xFF\xE1".pack('n', strlen($tiff) + 8)."Exif\0\0".$tiff;
        $bytes = substr($bytes, 0, 2).$app1.substr($bytes, 2);
    }

    return UploadedFile::fake()->createWithContent("photo.{$type}", $bytes);
}

function variantSize(Media $media, int $width): array
{
    $info = getimagesizefromstring(Storage::disk('public')->get($media->variants[(string) $width]));

    return [$info[0], $info[1], $info['mime']];
}

test('large images get webp copies at every smaller width', function () {
    $this->post(route('cp.media.store'), ['file' => realImage(2000, 1000)])->assertSessionHasNoErrors();

    $media = Media::sole();

    expect(array_keys($media->variants))->toBe([400, 800, 1600])
        ->and(variantSize($media, 400))->toBe([400, 200, 'image/webp'])
        ->and(variantSize($media, 1600))->toBe([1600, 800, 'image/webp'])
        ->and($media->thumb_url)->toBe(Storage::disk('public')->url($media->variants['400']))
        ->and($media->urlFor(700))->toBe(Storage::disk('public')->url($media->variants['800']))
        ->and($media->urlFor(3000))->toBe($media->url)
        ->and($media->srcset())->toContain('400w')->toContain('1600w')->toContain($media->url.' 2000w');
});

test('only widths smaller than the original are made', function () {
    $this->post(route('cp.media.store'), ['file' => realImage(600, 300)]);

    expect(array_keys(Media::sole()->variants))->toBe([400]);
});

test('small images, svgs and gifs keep only the original', function (UploadedFile $file) {
    $this->post(route('cp.media.store'), ['file' => $file])->assertSessionHasNoErrors();

    $media = Media::sole();

    expect($media->variants)->toBeNull()
        ->and($media->thumb_url)->toBe($media->url)
        ->and($media->srcset())->toBe('');
})->with([
    'small png' => fn () => realImage(300, 200),
    'gif' => fn () => realImage(1200, 800, 'gif'),
    'svg' => fn () => UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 900 900"><rect width="900" height="900"/></svg>'),
]);

test('transparency is kept in the copies', function () {
    $this->post(route('cp.media.store'), ['file' => realImage(1000, 1000)]);

    $variant = imagecreatefromstring(Storage::disk('public')->get(Media::sole()->variants['400']));

    expect(imagecolorsforindex($variant, imagecolorat($variant, 2, 2))['alpha'])->toBeGreaterThan(100)
        ->and(imagecolorsforindex($variant, imagecolorat($variant, 300, 300))['alpha'])->toBe(0);
});

test('photos rotated by the camera are stored upright', function () {
    // 1200x800 pixels, tagged "rotate 90 degrees clockwise" (orientation 6).
    $this->post(route('cp.media.store'), ['file' => realImage(1200, 800, 'jpg', orientation: 6)]);

    $media = Media::sole();

    expect([$media->width, $media->height])->toBe([800, 1200])
        ->and(variantSize($media, 400))->toBe([400, 600, 'image/webp']);
});

test('deleting an image for good removes its copies', function () {
    $this->post(route('cp.media.store'), ['file' => realImage(2000, 1000)]);
    $media = Media::sole();

    $this->delete(route('cp.media.destroy', $media));
    $this->delete(route('cp.trash.media.destroy', $media->id));

    foreach ($media->variants as $path) {
        Storage::disk('public')->assertMissing($path);
    }
});

test('the command creates copies for images uploaded before', function () {
    $this->post(route('cp.media.store'), ['file' => realImage(1000, 500)]);
    $media = Media::sole();
    Storage::disk('public')->delete($media->variants);
    $media->forceFill(['variants' => null])->save();

    $this->artisan('app:image-variants')->assertSuccessful();

    expect(array_keys($media->fresh()->variants))->toBe([400, 800]);
    Storage::disk('public')->assertExists($media->fresh()->variants['800']);

    $this->artisan('app:image-variants')->expectsOutputToContain('already has')->assertSuccessful();
});

test('the site serves resized copies with srcset', function () {
    $this->post(route('cp.media.store'), ['file' => realImage(2000, 1000)]);
    $media = Media::sole();

    $template = Template::factory()->create([
        'handle' => 'recipes',
        'fields' => [
            ['handle' => 'photo', 'label' => 'Photo', 'type' => 'image', 'required' => false, 'options' => []],
            ['handle' => 'method', 'label' => 'Method', 'type' => 'markdown', 'required' => false, 'options' => []],
        ],
        'layout' => '{{ photo }}{{ method }}',
    ]);
    Post::factory()->published()->for($template)->create([
        'slug' => 'soup',
        'thumbnail_id' => $media->id,
        'data' => ['photo' => $media->id, 'method' => "![A bowl]({$media->url})"],
    ]);

    $srcset = e($media->srcset());

    // Listing card, image field and markdown image all get the copies.
    $this->get('/recipes')->assertSee('srcset="'.$srcset.'"', false)->assertSee('src="'.e($media->urlFor(800)).'"', false);

    $this->get('/recipes/soup')
        ->assertSee('<img src="'.$media->url.'" srcset="'.$srcset.'" sizes="(min-width: 768px) 768px, 100vw" alt="" width="2000" height="1000" loading="lazy">', false)
        ->assertSee('<img src="'.$media->url.'" alt="A bowl" srcset="'.$srcset.'" sizes="(min-width: 768px) 768px, 100vw" width="2000" height="1000" loading="lazy">', false);
});
