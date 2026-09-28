<?php

use App\Models\Post;
use App\Models\Template;

beforeEach(function () {
    $this->template = Template::factory()->create([
        'name' => 'Recipes',
        'handle' => 'recipes',
        'fields' => [
            ['handle' => 'ingredients', 'label' => 'Ingredients', 'type' => 'list', 'required' => false, 'options' => []],
            ['handle' => 'method', 'label' => 'Method', 'type' => 'markdown', 'required' => false, 'options' => []],
            ['handle' => 'servings', 'label' => 'Servings', 'type' => 'number', 'required' => false, 'options' => []],
        ],
    ]);
});

function recipe(array $attributes, array $data = []): Post
{
    return Post::factory()->published()->for(test()->template)->create([...$attributes, 'data' => $data]);
}

test('the search page renders without a query', function () {
    $this->get(route('search'))->assertOk()->assertSee('Looking for something?');
});

test('posts are found by title and field content', function () {
    recipe(['title' => 'Carbonara'], ['ingredients' => ['Guanciale', 'Pecorino']]);
    recipe(['title' => 'Pancakes'], ['method' => 'Whisk the **flour** and eggs.']);

    $this->get(route('search', ['q' => 'carbonara']))->assertSee('Carbonara')->assertDontSee('Pancakes');
    $this->get(route('search', ['q' => 'pecorino']))->assertSee('Carbonara')->assertDontSee('Pancakes');
    $this->get(route('search', ['q' => 'flour']))
        ->assertSee('Pancakes')
        ->assertSee('<mark>flour</mark>', false)
        ->assertDontSee('**', false);
});

test('every word must match', function () {
    recipe(['title' => 'Tomato soup']);
    recipe(['title' => 'Tomato salad']);

    $this->get(route('search', ['q' => 'tomato soup']))
        ->assertSee('1 result')
        ->assertSee('Tomato soup')
        ->assertDontSee('Tomato salad');
});

test('search ignores accents and case', function () {
    recipe(['title' => 'Pão de Queijo']);

    $this->get(route('search', ['q' => 'PAO']))->assertSee('Pão de Queijo');
    $this->get(route('search', ['q' => 'queíjo']))->assertSee('Pão de Queijo');
});

test('drafts are not searchable', function () {
    Post::factory()->for($this->template)->create(['title' => 'Secret stew']);

    $this->get(route('search', ['q' => 'stew']))->assertSee('0 results')->assertDontSee('Secret stew');
});

test('like wildcards in the query are matched literally', function () {
    recipe(['title' => '100% whole wheat']);
    recipe(['title' => 'Plain bread']);

    $this->get(route('search', ['q' => '%']))->assertSee('1 result')->assertDontSee('Plain bread');
});

test('the search index is kept up to date', function () {
    $post = recipe(['title' => 'Old name']);

    $post->update(['title' => 'New name']);

    expect($post->fresh()->search_index)->toContain('new name')->not->toContain('old name');
});

test('search is reserved as a template handle', function () {
    expect(Template::RESERVED_HANDLES)->toContain('search');
});

test('the header has a search box', function () {
    $this->get(route('home'))->assertSee('role="search"', false);
});
