<?php

use App\Cms\PictureOfTheDay;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;

// What Wikimedia's featured feed answers, trimmed to the picture.
function featuredFeed(array $image = []): array
{
    return ['image' => array_replace_recursive([
        'title' => 'File:Gotland Grand National October 2023 12.jpg',
        'thumbnail' => [
            'source' => 'https://thumb.wikimedia.org/wikipedia/commons/thumb/d/d1/Gotland.jpg/960px-Gotland.jpg?utm_source=commons.wikimedia.org',
            'width' => 640,
            'height' => 864,
        ],
        'file_page' => 'https://commons.wikimedia.org/wiki/File:Gotland.jpg',
        'artist' => ['text' => 'Arild Vågen'],
        'license' => ['type' => 'CC BY-SA 4.0', 'url' => 'https://creativecommons.org/licenses/by-sa/4.0'],
        'description' => ['text' => 'The world’s largest Enduro competition, first run in 1984.'],
        'structured' => ['captions' => ['en' => 'Gotland Grand National 2023']],
    ], $image)];
}

beforeEach(function () {
    Date::setTestNow('2026-10-11 15:00:00');
});

test('the home page shows the picture of the day, opening into a dialog', function () {
    Http::fake(['api.wikimedia.org/*' => Http::response(featuredFeed())]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('data-dialog-open="picture-of-the-day"', false)
        ->assertSee('Gotland Grand National 2023')
        // The thumbnail, without the tracking parameters.
        ->assertSee('src="https://thumb.wikimedia.org/wikipedia/commons/thumb/d/d1/Gotland.jpg/960px-Gotland.jpg"', false)
        ->assertSee('By Arild Vågen')
        ->assertSee('CC BY-SA 4.0')
        ->assertSee('href="https://commons.wikimedia.org/wiki/File:Gotland.jpg"', false);

    Http::assertSent(fn ($request) => $request->url() === 'https://api.wikimedia.org/feed/v1/wikipedia/en/featured/2026/10/11'
        && str_contains($request->header('User-Agent')[0], config('app.url')));
});

test('it is fetched once a day', function () {
    Http::fake(['api.wikimedia.org/*' => Http::response(featuredFeed())]);

    $this->get(route('home'));
    $this->get(route('home'));

    Http::assertSentCount(1);

    Date::setTestNow('2026-10-12 08:00:00');
    $this->get(route('home'));

    Http::assertSentCount(2);
});

test('without a caption the file name is used', function () {
    Http::fake(['api.wikimedia.org/*' => Http::response(featuredFeed(['structured' => ['captions' => ['en' => '']]]))]);

    expect(app(PictureOfTheDay::class)->today()['caption'])->toBe('Gotland Grand National October 2023 12');
});

test('the page still works when Wikimedia does not, and asks again later', function () {
    Http::fake(['api.wikimedia.org/*' => Http::response('Down', 503)]);

    $this->get(route('home'))->assertOk()->assertDontSee('data-dialog-open="picture-of-the-day"', false);
    $this->get(route('home'));

    Http::assertSentCount(1);

    $this->travel(31)->minutes();
    $this->get(route('home'));

    Http::assertSentCount(2);
});

test('days without a picture show none', function () {
    Http::fake(['api.wikimedia.org/*' => Http::response(['tfa' => []])]);

    expect(app(PictureOfTheDay::class)->today())->toBeNull();
});
