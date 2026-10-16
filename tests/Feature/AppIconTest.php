<?php

use App\Cms\AppIcon;
use App\Cms\PushNotifications;
use App\Cms\Settings;
use App\Models\Media;
use App\Models\PushSubscription;
use App\Models\User;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Storage;
use Minishlink\WebPush\MessageSentReport;
use Minishlink\WebPush\WebPush;

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
});

/**
 * An uploaded picture of one color, of the given size.
 *
 * @param  array{int, int, int}  $rgb
 */
function picture(array $rgb, int $width = 100, int $height = 50, string $mime = 'image/png'): Media
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, ...$rgb));
    ob_start();
    imagepng($image);
    $path = 'media/'.Str::random(8).'.png';
    Storage::disk('public')->put($path, (string) ob_get_clean());

    return Media::factory()->create(['path' => $path, 'mime_type' => $mime]);
}

/**
 * The color of a pixel of the icon, at a fraction of its width and height.
 *
 * @return array{int, int, int}
 */
function iconPixel(string $png, float $x, float $y): array
{
    $image = imagecreatefromstring($png);
    $rgb = imagecolorat($image, (int) (imagesx($image) * $x), (int) (imagesy($image) * $y));

    return [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];
}

test('the icon has the logo in the middle, on the card color', function () {
    app(Settings::class)->update(['logo_id' => picture([200, 30, 30])->id]);

    $png = app(AppIcon::class)->render(192);

    expect(getimagesizefromstring($png)[0])->toBe(192)
        ->and(iconPixel($png, 0.5, 0.5))->toBe([200, 30, 30])
        ->and(iconPixel($png, 0.02, 0.02))->toBe([255, 252, 245])
        // A wide logo leaves room above and below it.
        ->and(iconPixel($png, 0.5, 0.2))->toBe([255, 252, 245]);
});

test('the icon has the logo\'s background, when it has one', function () {
    app(Settings::class)->update([
        'logo_id' => picture([255, 255, 255])->id,
        'logo_background' => ['type' => 'solid', 'from' => '#123456', 'to' => '#123456', 'angle' => 135],
    ]);

    $png = app(AppIcon::class)->render(192);

    expect(iconPixel($png, 0.02, 0.02))->toBe([0x12, 0x34, 0x56])
        ->and(iconPixel($png, 0.5, 0.5))->toBe([255, 255, 255]);
});

test('an svg logo is drawn too', function (string $size) {
    Storage::disk('public')->put('media/logo.svg', '<svg xmlns="http://www.w3.org/2000/svg" '.$size.'><rect width="200" height="100" fill="#1f6feb"/></svg>');
    $svg = Media::factory()->create(['mime_type' => 'image/svg+xml', 'path' => 'media/logo.svg']);
    app(Settings::class)->update(['logo_id' => $svg->id]);

    $png = app(AppIcon::class)->render(192);

    // A wide logo: blue in the middle, the card above and below it.
    expect(iconPixel($png, 0.5, 0.5))->toBe([0x1F, 0x6F, 0xEB])
        ->and(iconPixel($png, 0.5, 0.2))->toBe([255, 252, 245]);
})->with([
    'with a view box' => 'viewBox="0 0 200 100"',
    'with a width and height' => 'width="200" height="100"',
]);

test('an svg logo that can not be read gives way to the site icon, then to the first letter', function () {
    Storage::disk('public')->put('media/logo.svg', 'not an svg');
    $svg = Media::factory()->create(['mime_type' => 'image/svg+xml', 'path' => 'media/logo.svg']);
    app(Settings::class)->update(['logo_id' => $svg->id, 'favicon_id' => picture([20, 160, 60], 64, 64)->id]);

    expect(iconPixel(app(AppIcon::class)->render(192), 0.5, 0.5))->toBe([20, 160, 60]);

    app(Settings::class)->update(['favicon_id' => null, 'site_name' => 'Garmr']);

    // The letter badge: ink all around.
    expect(iconPixel(app(AppIcon::class)->render(192), 0.02, 0.02))->toBe([34, 29, 23]);
});

test('the icon is served at its sizes, kept for good at its current address', function () {
    $icon = app(AppIcon::class);

    $this->get($icon->url(512))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');

    $this->get(route('app-icon', ['size' => 64]))->assertNotFound();
});

test('a new logo gives the icon a new address', function () {
    $before = app(AppIcon::class)->url();

    app(Settings::class)->update(['logo_id' => picture([1, 2, 3])->id]);

    expect(app(AppIcon::class)->url())->not->toBe($before);
});

test('the icon is used by home screens, the app manifest and push notifications', function () {
    $icon = app(AppIcon::class);

    $this->get(route('home'))->assertSee('<link rel="apple-touch-icon" href="'.$icon->url(192).'">', false);
    $this->get(route('manifest'))
        ->assertJsonPath('icons.0', ['src' => $icon->url(192), 'sizes' => '192x192', 'type' => 'image/png'])
        ->assertJsonPath('icons.1.src', $icon->url(512));

    config(['push.public_key' => 'key', 'push.private_key' => 'key']);
    $user = User::factory()->member()->create();
    $subscription = new PushSubscription(['endpoint' => 'https://push.example/a', 'endpoint_hash' => PushSubscription::hashEndpoint('https://push.example/a'), 'public_key' => 'p', 'auth_token' => 'a']);
    $subscription->user_id = $user->id;
    $subscription->save();

    $webPush = Mockery::mock(WebPush::class);
    $webPush->shouldReceive('queueNotification')->once()->withArgs(fn ($subscription, $payload) => json_decode($payload, true)['icon'] === $icon->url());
    $webPush->shouldReceive('flush')->andReturnUsing(function () {
        yield new MessageSentReport(new Request('POST', 'https://push.example/a'), new Response(201));
    });
    $push = Mockery::mock(PushNotifications::class)->makePartial()->shouldAllowMockingProtectedMethods();
    $push->shouldReceive('webPush')->andReturn($webPush);

    expect($push->send($user, ['title' => 'Hi', 'body' => 'There', 'tag' => 't', 'url' => '/']))->toBe(1);
});
