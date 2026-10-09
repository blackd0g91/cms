<?php

use App\Models\Post;
use App\Models\Template;

test('the home page lists the latest published posts', function () {
    $template = Template::factory()->create(['name' => 'Cheatsheets']);
    Post::factory()->published()->for($template)->create(['title' => 'Git basics']);
    Post::factory()->for($template)->create(['title' => 'Unfinished draft']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('Git basics')
        ->assertSee('Cheatsheets')
        ->assertDontSee('Unfinished draft');
});

test('the sidebar lists every template', function () {
    Template::factory()->create(['name' => 'Recipes', 'handle' => 'recipes']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('href="'.url('/recipes').'"', false);
});

test('cards show the summary written for a post, and how long it takes to read', function () {
    $template = Template::factory()->create(['fields' => [
        ['handle' => 'body', 'label' => 'Body', 'type' => 'markdown', 'required' => false, 'options' => []],
    ]]);
    Post::factory()->published()->for($template)->create([
        'title' => 'Bread',
        'summary' => 'A slow loaf for <weekends>.',
        'data' => ['body' => str_repeat('Knead it well. ', 140)],
    ]);
    Post::factory()->published()->for($template)->create(['title' => 'Untold', 'summary' => null, 'data' => ['body' => 'Not on the card.']]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('A slow loaf for &lt;weekends&gt;.', false)
        ->assertDontSee('Not on the card.')
        ->assertSee('3 min read')
        ->assertSee('1 min read');

    // Every other card on the site is the same partial.
    $this->get(url($template->handle))->assertSee('A slow loaf for')->assertSee('3 min read');
});

test('without an intro, the home page is only the posts', function () {
    Post::factory()->published()->for(Template::factory()->create())->create(['title' => 'Git basics']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<h1 class="sr-only">Latest entries</h1>', false)
        ->assertDontSee('home-hero', false)
        ->assertSee('Git basics');
});

test('pages have the aurora and contour lines behind them, in the templates\' colors', function () {
    Template::factory()->create(['name' => 'Apps', 'color' => '#2563eb']);
    $notes = Template::factory()->create(['name' => 'Notes', 'color' => null]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('class="backdrop-aurora"', false)
        ->assertSee('--c1: #2563eb; --c2: '.$notes->accentColor().'; --c3: oklch(0.58 0.13 45)', false)
        ->assertSee('data-contours', false)
        ->assertDontSee('data-dot-ripples', false);
});
