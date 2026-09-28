<?php

use App\Cms\Settings;
use App\Models\Media;
use App\Models\Post;
use App\Models\Template;
use App\Models\User;

beforeEach(function () {
    app(Settings::class)->update(['site_name' => 'Garmr', 'tagline' => 'Notes and recipes']);

    $this->template = Template::factory()->create([
        'name' => 'Recipes',
        'handle' => 'recipes',
        'description' => 'Things I cook',
        'fields' => [['handle' => 'method', 'label' => 'Method', 'type' => 'markdown', 'required' => false, 'options' => []]],
        'layout' => '{{ method }}',
    ]);
});

test('posts have a description, canonical url and link preview tags', function () {
    $media = Media::factory()->create();
    Post::factory()->published()->for($this->template)->create([
        'title' => 'Soup',
        'slug' => 'soup',
        'thumbnail_id' => $media->id,
        'data' => ['method' => 'Boil **water** and add salt.'],
    ]);

    $this->get('/recipes/soup')
        ->assertOk()
        ->assertSee('<meta name="description" content="Boil water and add salt.">', false)
        ->assertSee('<link rel="canonical" href="'.url('/recipes/soup').'">', false)
        ->assertSee('<meta property="og:title" content="Soup">', false)
        ->assertSee('<meta property="og:type" content="article">', false)
        ->assertSee('<meta property="og:image" content="'.$media->url.'">', false)
        ->assertSee('<meta name="twitter:card" content="summary_large_image">', false)
        ->assertSee('article:published_time', false)
        ->assertSee('href="'.route('site.template.feed', $this->template).'"', false);
});

test('pages fall back to the tagline and site logo', function () {
    $logo = Media::factory()->create();
    app(Settings::class)->update(['logo_id' => $logo->id]);

    $this->get(route('home'))
        ->assertSee('<meta name="description" content="Notes and recipes">', false)
        ->assertSee('<meta property="og:image" content="'.$logo->url.'">', false)
        ->assertSee('<meta name="twitter:card" content="summary">', false)
        ->assertSee('<link rel="alternate" type="application/rss+xml" title="Garmr" href="'.route('feed').'">', false);

    $this->get('/recipes')->assertSee('<meta name="description" content="Things I cook">', false);
});

test('draft previews and search results are not indexed', function () {
    Post::factory()->for($this->template)->create(['slug' => 'draft']);

    $this->actingAs(User::factory()->create())
        ->get('/recipes/draft')
        ->assertSee('<meta name="robots" content="noindex">', false)
        ->assertDontSee('rel="canonical"', false);

    $this->get(route('search', ['q' => 'x']))->assertSee('<meta name="robots" content="noindex">', false);
});

test('the control panel asks not to be indexed', function () {
    $this->get(route('cp.login'))->assertSee('<meta name="robots" content="noindex, nofollow">', false);
});

test('the rss feed lists published posts with their content', function () {
    Post::factory()->published()->for($this->template)->create([
        'title' => 'Soup & bread',
        'slug' => 'soup',
        'data' => ['method' => '**Boil** it.'],
    ]);
    Post::factory()->for($this->template)->create(['title' => 'Secret draft']);

    $response = $this->get(route('feed'))->assertOk()->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

    $xml = simplexml_load_string($response->getContent());

    expect((string) $xml->channel->title)->toBe('Garmr')
        ->and($xml->channel->item)->toHaveCount(1)
        ->and((string) $xml->channel->item->title)->toBe('Soup & bread')
        ->and((string) $xml->channel->item->link)->toBe(url('/recipes/soup'))
        ->and((string) $xml->channel->item->category)->toBe('Recipes')
        ->and((string) $xml->channel->item->children('content', true)->encoded)->toContain('<strong>Boil</strong>');
});

test('each template has its own feed', function () {
    Post::factory()->published()->for($this->template)->create(['title' => 'Soup']);
    Post::factory()->published()->create(['title' => 'Elsewhere']);

    $xml = simplexml_load_string($this->get('/recipes/feed.xml')->assertOk()->getContent());

    expect((string) $xml->channel->title)->toBe('Recipes - Garmr')
        ->and($xml->channel->item)->toHaveCount(1)
        ->and((string) $xml->channel->item->title)->toBe('Soup');
});

test('the sitemap lists public pages only', function () {
    Post::factory()->published()->for($this->template)->create(['slug' => 'soup']);
    Post::factory()->for($this->template)->create(['slug' => 'draft']);

    $response = $this->get(route('sitemap'))->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $locs = [];
    foreach (simplexml_load_string($response->getContent())->url as $url) {
        $locs[] = (string) $url->loc;
    }

    expect($locs)->toBe([route('home'), url('/recipes'), url('/recipes/soup')]);
});

test('robots.txt blocks the control panel and points to the sitemap', function () {
    $this->get('/robots.txt')
        ->assertOk()
        ->assertSee('Disallow: /cp')
        ->assertSee('Sitemap: '.route('sitemap'));
});
