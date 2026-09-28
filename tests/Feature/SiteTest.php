<?php

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
        ->assertSee('<h1>Soup</h1>', false)
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
        ->assertSee('This post is a draft');
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
