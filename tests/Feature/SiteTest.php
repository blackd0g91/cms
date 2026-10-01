<?php

use App\Models\Media;
use App\Models\Post;
use App\Models\Template;
use App\Models\User;

beforeEach(function () {
    $this->template = Template::factory()->create([
        'handle' => 'recipes',
        'fields' => [
            ['handle' => 'intro', 'label' => 'Intro', 'type' => 'text', 'required' => false, 'options' => []],
            ['handle' => 'method', 'label' => 'Method', 'type' => 'markdown', 'required' => false, 'options' => []],
            ['handle' => 'ingredients', 'label' => 'Ingredients', 'type' => 'list', 'required' => false, 'options' => []],
            ['handle' => 'vegan', 'label' => 'Vegan', 'type' => 'boolean', 'required' => false, 'options' => []],
        ],
        'layout' => implode('', [
            '<h1>{{ title }}</h1>',
            '<p class="intro">{{ intro }}</p>',
            '{{ method }}',
            '<ul>{{# ingredients }}<li>{{ . }}</li>{{/ ingredients }}</ul>',
            '{{# vegan }}<span>Vegan!</span>{{/ vegan }}',
        ]),
    ]);
});

test('a published post is rendered through its template layout', function () {
    Post::factory()->published()->for($this->template)->create([
        'title' => 'Soup',
        'slug' => 'soup',
        'data' => [
            'intro' => '<script>alert(1)</script>',
            'method' => '**Boil** it.',
            'ingredients' => ['Water', 'Salt'],
            'vegan' => true,
        ],
    ]);

    $this->get('/recipes/soup')
        ->assertOk()
        ->assertSee('>Soup</h1>', false)
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertSee('<strong>Boil</strong>', false)
        ->assertSee('<li>Water</li><li>Salt</li>', false)
        ->assertSee('Vegan!');
});

test('code blocks in markdown are syntax highlighted', function () {
    Post::factory()->published()->for($this->template)->create([
        'slug' => 'php',
        'data' => ['method' => "```php\n\$total = 1 + 2;\n```\n\n| Key | Action |\n| --- | --- |\n| a | b |"],
    ]);

    $this->get('/recipes/php')
        ->assertOk()
        ->assertSee('class="phiki language-php', false)
        ->assertSee('--phiki-dark-color', false)
        ->assertSee('<table>', false);
});

test('drafts are hidden from guests', function () {
    Post::factory()->for($this->template)->create(['slug' => 'secret']);

    $this->get('/recipes/secret')->assertNotFound();
});

test('drafts can be previewed when logged in', function () {
    Post::factory()->for($this->template)->create(['slug' => 'secret']);

    $this->actingAs(User::factory()->create())
        ->get('/recipes/secret')
        ->assertOk()
        ->assertSee('Draft preview');
});

test('posts are found by slug within their template', function () {
    Post::factory()->published()->create(['slug' => 'soup']);

    $this->get('/recipes/soup')->assertNotFound();
});

test('the template page lists published posts', function () {
    Post::factory()->published()->for($this->template)->create(['title' => 'Visible']);
    Post::factory()->for($this->template)->create(['title' => 'Hidden draft']);

    $this->get('/recipes')
        ->assertOk()
        ->assertSee('Visible')
        ->assertDontSee('Hidden draft');
});

test('unknown templates are not found', function () {
    $this->get('/nope')->assertNotFound();
});

test('the control panel is not captured by the public routes', function () {
    $this->get('/cp/login')->assertOk();
});

test('image fields render as images in layouts', function () {
    $media = Media::factory()->create(['alt' => 'Soup "bowl"', 'width' => 640, 'height' => 480]);
    $template = Template::factory()->create([
        'handle' => 'photos',
        'fields' => [
            ['handle' => 'photo', 'label' => 'Photo', 'type' => 'image', 'required' => false, 'options' => []],
            ['handle' => 'missing', 'label' => 'Missing', 'type' => 'image', 'required' => false, 'options' => []],
        ],
        'layout' => '{{ photo }}|{{# photo }}<a href="{{ url }}">{{ alt }}</a>{{/ photo }}|{{# missing }}never{{/ missing }}{{^ missing }}none{{/ missing }}',
    ]);
    Post::factory()->published()->for($template)->create([
        'slug' => 'one',
        'data' => ['photo' => $media->id, 'missing' => 12345],
    ]);

    $this->get('/photos/one')
        ->assertOk()
        ->assertSee('<img src="'.$media->url.'" alt="Soup &quot;bowl&quot;" width="640" height="480" loading="lazy">', false)
        ->assertSee('<a href="'.$media->url.'">Soup &quot;bowl&quot;</a>', false)
        ->assertSee('|none', false)
        ->assertDontSee('never');
});

test('the post page shows the title and thumbnail above the layout', function () {
    $media = Media::factory()->create(['alt' => 'A bowl']);
    Post::factory()->published()->for($this->template)->create([
        'title' => 'Soup',
        'slug' => 'soup',
        'thumbnail_id' => $media->id,
    ]);

    $this->get('/recipes/soup')
        ->assertOk()
        ->assertSeeInOrder([$this->template->name, 'src="'.$media->url.'"', 'Soup</h1>', 'min read'], false);
});

test('listings show thumbnails when a post has one', function () {
    $media = Media::factory()->create();
    Post::factory()->published()->for($this->template)->create(['title' => 'With', 'thumbnail_id' => $media->id]);
    Post::factory()->published()->for($this->template)->create(['title' => 'Without']);

    $this->get('/recipes')->assertSee('src="'.$media->url.'"', false)->assertSee('aria-hidden="true"', false);
    $this->get(route('home'))->assertSee('src="'.$media->url.'"', false);
    $this->get(route('search', ['q' => 'with']))->assertSee('src="'.$media->url.'"', false);
});

test('posts without a thumbnail get a lettered placeholder in the accent color', function () {
    Post::factory()->published()->for($this->template)->create(['title' => 'plain toast']);

    $this->get('/recipes')
        ->assertOk()
        ->assertSee('style="--hue: '.$this->template->hue().'; --accent-base: initial"', false)
        ->assertSeeInOrder(['text-accent', '>P</span>'], false);
});

test('templates get distinct accent hues', function () {
    $hues = Template::factory()->count(5)->create()->map->hue();

    expect($hues->unique())->toHaveCount(5)
        ->and($hues->every(fn (int $hue) => $hue >= 0 && $hue < 360))->toBeTrue();
});

test('posts show a reading time', function () {
    $post = Post::factory()->published()->for($this->template)->create([
        'slug' => 'long',
        'data' => ['method' => str_repeat('word ', 450)],
    ]);

    expect($post->readingMinutes())->toBe(3);

    $this->get('/recipes/long')->assertSee('3 min read');
});

test('a chosen template color is used for its accents', function () {
    $this->template->update(['color' => '#c2410c']);
    Post::factory()->published()->for($this->template)->create(['slug' => 'soup']);

    $style = 'style="--hue: '.$this->template->hue().'; --accent-base: #c2410c"';

    $this->get('/recipes')->assertSee($style, false);
    $this->get('/recipes/soup')->assertSee($style, false);
    $this->get(route('home'))->assertSee($style, false);
});

test('posts with several headings get a table of contents', function () {
    Post::factory()->published()->for($this->template)->create([
        'slug' => 'git',
        'data' => ['method' => "## Setup\n\ntext\n\n### Config\n\ntext\n\n## Branches\n\ntext"],
    ]);

    $this->get('/recipes/git')
        ->assertOk()
        ->assertSee('<h2 id="setup">Setup</h2>', false)
        ->assertSee('data-toc', false)
        ->assertSeeInOrder(['href="#setup"', 'href="#config"', 'href="#branches"'], false);
});

test('posts written with # headings in markdown get them in the table of contents', function () {
    Post::factory()->published()->for($this->template)->create([
        'slug' => 'guide',
        'data' => ['method' => "# Setup\n\ntext\n\n## Install\n\ntext\n\n# Usage\n\ntext"],
    ]);

    $this->get('/recipes/guide')
        ->assertOk()
        ->assertSee('<h1 id="setup">Setup</h1>', false)
        ->assertSeeInOrder(['href="#setup"', 'href="#install"', 'href="#usage"'], false);
});

test('posts with fewer than two headings have no table of contents', function () {
    Post::factory()->published()->for($this->template)->create([
        'slug' => 'short',
        'data' => ['method' => "## Only one\n\ntext"],
    ]);

    $this->get('/recipes/short')->assertOk()->assertDontSee('data-toc', false);
});

test('posts have a keep screen on button, hidden until the browser supports it', function () {
    Post::factory()->published()->for($this->template)->create(['slug' => 'soup']);

    $this->get('/recipes/soup')
        ->assertOk()
        ->assertSee('data-wake-lock', false)
        ->assertSeeInOrder(['data-wake-lock', 'hidden', 'Keep screen on'], false);
});

test('the site has a theme toggle, with the choice applied before the page draws', function () {
    $response = $this->get(route('home'))->assertOk();

    $html = $response->getContent();

    expect($html)->toContain('data-theme-toggle')
        ->toContain("localStorage.getItem('site.theme')")
        // The theme script must run before the stylesheet is loaded.
        ->and(strpos($html, "localStorage.getItem('site.theme')"))->toBeLessThan(strpos($html, 'rel="stylesheet"'));
});

test('emoji shortcodes show as emoji on posts', function () {
    Post::factory()->published()->for($this->template)->create(['slug' => 'party', 'data' => ['method' => 'Party time :tada:']]);

    $this->get('/recipes/party')->assertSee('Party time 🎉');
});
