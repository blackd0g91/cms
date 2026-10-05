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

test('cards show the first lines of each post and how long it takes to read', function () {
    $template = Template::factory()->create(['fields' => [
        ['handle' => 'body', 'label' => 'Body', 'type' => 'markdown', 'required' => false, 'options' => []],
    ]]);
    Post::factory()->published()->for($template)->create([
        'title' => 'Bread',
        'data' => ['body' => "## Before you start\n\nYou need **flour**, water and {{ recipe-amount:5 g }} yeast. ".str_repeat('Knead it well. ', 140)],
    ]);
    Post::factory()->published()->for($template)->create(['title' => 'Empty', 'data' => ['body' => '']]);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('You need flour, water and 5 g yeast. Knead it well.')
        ->assertDontSee('Before you start You need')
        ->assertDontSee('**flour**')
        ->assertSee('3 min read')
        ->assertSee('1 min read');

    // Every other card on the page is the same partial.
    $this->get(url($template->handle))->assertSee('You need flour, water and 5 g yeast.')->assertSee('3 min read');
});
