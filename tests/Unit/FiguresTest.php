<?php

use App\Cms\Markdown;
use App\Cms\ResponsiveImages;
use App\Models\Media;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

function figures(string $markdown): string
{
    return trim(app(Markdown::class)->render($markdown));
}

test('an image alone on its line with a title gets it as a caption', function () {
    expect(figures('![A loaf](https://example.com/bread.jpg "Fresh from the oven")'))
        ->toBe('<figure><img src="https://example.com/bread.jpg" alt="A loaf" /><figcaption>Fresh from the oven</figcaption></figure>');
});

test('captions are text, not html', function () {
    expect(figures('![A loaf](https://example.com/bread.jpg "Fresh <b>hot</b>")'))
        ->toContain('<figcaption>Fresh &lt;b&gt;hot&lt;/b&gt;</figcaption>')
        ->not->toContain('<b>');
});

test('images without a title, within text, or inside links are left as they are', function (string $markdown, string $html) {
    expect(figures($markdown))->toBe($html);
})->with([
    'no title' => ['![A loaf](https://example.com/bread.jpg)', '<p><img src="https://example.com/bread.jpg" alt="A loaf" /></p>'],
    'within text' => ['A tip ![icon](https://example.com/i.png "Hint") here.', '<p>A tip <img src="https://example.com/i.png" alt="icon" title="Hint" /> here.</p>'],
    'linked' => ['[![Logo](https://example.com/l.png "Home")](https://example.com)', '<p><a href="https://example.com"><img src="https://example.com/l.png" alt="Logo" title="Home" /></a></p>'],
    'two images' => ['![A](https://example.com/a.png "One") ![B](https://example.com/b.png "Two")', '<p><img src="https://example.com/a.png" alt="A" title="One" /> <img src="https://example.com/b.png" alt="B" title="Two" /></p>'],
]);

test('uploaded images in a figure still get their resized versions', function () {
    $media = Media::factory()->create(['path' => 'media/bread.jpg', 'width' => 1600, 'height' => 1200]);
    $media->forceFill(['variants' => ['400' => 'media/variants/bread-400.webp']])->save();

    $html = app(ResponsiveImages::class)->apply(figures("![A loaf]({$media->url} \"Fresh\")"));

    expect($html)->toContain('<figure><img src="'.$media->url.'" alt="A loaf" srcset=')
        ->toContain('width="1600" height="1200" loading="lazy">')
        ->toContain('<figcaption>Fresh</figcaption></figure>');
});
