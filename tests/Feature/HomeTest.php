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
