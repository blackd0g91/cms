<?php

use App\Cms\Oklch;
use App\Cms\ShareImage;
use App\Models\Template;

test('hex colors survive the round trip through oklch', function (string $hex) {
    [$r, $g, $b] = Oklch::fromHex($hex)->toRgb();

    expect(sprintf('#%02x%02x%02x', $r, $g, $b))->toBe($hex);
})->with(['#c2410c', '#2f7d4a', '#000000', '#ffffff', '#7c3aed']);

test('oklch colors convert to the same srgb as in browsers', function () {
    // oklch(0.58 0.13 45), the site's default accent.
    expect((new Oklch(0.58, 0.13, 45))->toRgb())->toBe([184, 94, 49]);
});

test('template accents are drawn as the site shows them, darkening chosen colors that are too light', function () {
    $chosen = new Template(['color' => '#c2410c']);
    $tooLight = new Template(['color' => '#ffe066']);

    expect($chosen->accentRgb())->toBe([194, 65, 12])
        ->and(Oklch::fromHex('#ffe066')->lightness)->toBeGreaterThan(0.68)
        ->and(round(Oklch::fromHex(vsprintf('#%02x%02x%02x', $tooLight->accentRgb()))->lightness, 2))->toBe(0.68);
});

test('emoji are left out of drawn text, other symbols are kept', function () {
    expect(ShareImage::printable('Soup 🍲 for two 👍🏽 ❤️ 🇧🇷'))->toBe('Soup for two')
        ->and(ShareImage::printable('Café © 2026 ™'))->toBe('Café © 2026 ™');
});
