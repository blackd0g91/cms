<?php

use App\Models\Post;
use App\Models\Template;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function templatePayload(array $overrides = []): array
{
    return [
        'name' => 'Recipes',
        'handle' => 'recipes',
        'description' => 'Things I cook',
        'fields' => [
            ['handle' => 'servings', 'label' => 'Servings', 'type' => 'number', 'required' => true, 'options' => []],
            ['handle' => 'difficulty', 'label' => 'Difficulty', 'type' => 'select', 'required' => false, 'options' => ['Easy', 'Hard']],
            ['handle' => 'ingredients', 'label' => 'Ingredients', 'type' => 'list', 'required' => false, 'options' => ['ignored']],
        ],
        'layout' => '<h1>{{ title }}</h1>',
        ...$overrides,
    ];
}

test('guests can not manage templates', function () {
    auth()->logout();

    $this->get(route('cp.templates.index'))->assertRedirect(route('cp.login'));
});

test('templates are listed with their post counts', function () {
    Post::factory()->count(2)->for($template = Template::factory()->create())->create();

    $this->get(route('cp.templates.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('cp/templates/Index')
            ->where('templates.0.id', $template->id)
            ->where('templates.0.posts_count', 2));
});

test('a template can be created', function () {
    $this->post(route('cp.templates.store'), templatePayload())
        ->assertRedirect(route('cp.templates.edit', Template::sole()));

    $template = Template::sole();

    expect($template->handle)->toBe('recipes')
        ->and($template->fields)->toHaveCount(3)
        ->and($template->fields[1]['options'])->toBe(['Easy', 'Hard'])
        ->and($template->fields[2]['options'])->toBe([]);
});

test('an empty layout is generated from the fields', function () {
    $this->post(route('cp.templates.store'), templatePayload(['layout' => '']));

    expect(Template::sole()->layout)
        ->toContain('{{ title }}')
        ->toContain('{{ servings }}')
        ->toContain('{{# ingredients }}<li>{{ . }}</li>{{/ ingredients }}');
});

test('a template can be updated', function () {
    $template = Template::factory()->create();

    $this->put(route('cp.templates.update', $template), templatePayload(['name' => 'Cheatsheets', 'handle' => 'cheatsheets']))
        ->assertRedirect(route('cp.templates.edit', $template));

    expect($template->fresh()->handle)->toBe('cheatsheets');
});

test('template validation', function (array $overrides, string $error) {
    $this->post(route('cp.templates.store'), templatePayload($overrides))
        ->assertSessionHasErrors($error);
})->with([
    'reserved handle' => [['handle' => 'cp'], 'handle'],
    'invalid handle' => [['handle' => 'My Recipes'], 'handle'],
    'reserved field handle' => [['fields' => [['handle' => 'title', 'label' => 'Title', 'type' => 'text']]], 'fields.0.handle'],
    'duplicate field handles' => [['fields' => [
        ['handle' => 'a', 'label' => 'A', 'type' => 'text'],
        ['handle' => 'a', 'label' => 'B', 'type' => 'text'],
    ]], 'fields.0.handle'],
    'unknown field type' => [['fields' => [['handle' => 'a', 'label' => 'A', 'type' => 'video']]], 'fields.0.type'],
    'select without options' => [['fields' => [['handle' => 'a', 'label' => 'A', 'type' => 'select', 'options' => []]]], 'fields.0.options'],
    'broken layout' => [['layout' => '{{# open }}never closed'], 'layout'],
]);

test('template handles must be unique', function () {
    Template::factory()->create(['handle' => 'recipes']);

    $this->post(route('cp.templates.store'), templatePayload())->assertSessionHasErrors('handle');
});

test('a template without posts can be deleted', function () {
    $template = Template::factory()->create();

    $this->delete(route('cp.templates.destroy', $template))->assertRedirect(route('cp.templates.index'));

    $this->assertModelMissing($template);
});

test('a template with posts can not be deleted', function () {
    $post = Post::factory()->create();

    $this->delete(route('cp.templates.destroy', $post->template))->assertSessionHasErrors('template');

    $this->assertModelExists($post->template);
});

test('renaming a field moves post data and updates the layout', function () {
    $template = Template::factory()->create([
        'fields' => [
            ['handle' => 'body', 'label' => 'Body', 'type' => 'markdown', 'required' => false, 'options' => []],
            ['handle' => 'notes', 'label' => 'Notes', 'type' => 'text', 'required' => false, 'options' => []],
        ],
        'layout' => '{{ body }}{{# notes }}<p>{{ notes }}</p>{{/ notes }}{{ bodyguard }}',
    ]);
    $post = Post::factory()->for($template)->create([
        'data' => ['body' => 'Stir well', 'notes' => 'Serve hot'],
        'updated_at' => now()->subWeek(),
    ]);

    $this->put(route('cp.templates.update', $template), templatePayload([
        'handle' => $template->handle,
        'fields' => [
            ['original_handle' => 'body', 'handle' => 'method', 'label' => 'Method', 'type' => 'markdown'],
            ['original_handle' => 'notes', 'handle' => 'notes', 'label' => 'Notes', 'type' => 'text'],
        ],
        'layout' => $template->layout,
    ]))->assertSessionHasNoErrors();

    $post->refresh();

    expect($post->data)->toBe(['notes' => 'Serve hot', 'method' => 'Stir well'])
        ->and($post->search_index)->toContain('stir well')
        ->and($post->updated_at->isSameDay(now()->subWeek()))->toBeTrue()
        ->and($template->fresh()->layout)->toBe('{{ method }}{{# notes }}<p>{{ notes }}</p>{{/ notes }}{{ bodyguard }}');
});

test('two field handles can be swapped', function () {
    $template = Template::factory()->create([
        'fields' => [
            ['handle' => 'a', 'label' => 'A', 'type' => 'text', 'required' => false, 'options' => []],
            ['handle' => 'b', 'label' => 'B', 'type' => 'text', 'required' => false, 'options' => []],
        ],
        'layout' => '{{ a }}-{{ b }}',
    ]);
    $post = Post::factory()->for($template)->create(['data' => ['a' => 'first', 'b' => 'second']]);

    $this->put(route('cp.templates.update', $template), templatePayload([
        'handle' => $template->handle,
        'fields' => [
            ['original_handle' => 'a', 'handle' => 'b', 'label' => 'A', 'type' => 'text'],
            ['original_handle' => 'b', 'handle' => 'a', 'label' => 'B', 'type' => 'text'],
        ],
        'layout' => $template->layout,
    ]))->assertSessionHasNoErrors();

    expect($post->fresh()->data)->toBe(['b' => 'first', 'a' => 'second'])
        ->and($template->fresh()->layout)->toBe('{{ b }}-{{ a }}');
});

test('original handles the template does not have are ignored', function () {
    $template = Template::factory()->create();
    $post = Post::factory()->for($template)->create(['data' => ['body' => 'Hello']]);

    $this->put(route('cp.templates.update', $template), templatePayload([
        'handle' => $template->handle,
        'fields' => [
            ['original_handle' => 'body', 'handle' => 'body', 'label' => 'Body', 'type' => 'markdown'],
            ['original_handle' => 'made_up', 'handle' => 'extra', 'label' => 'Extra', 'type' => 'text'],
        ],
    ]))->assertSessionHasNoErrors();

    expect($post->fresh()->data)->toBe(['body' => 'Hello']);
});
