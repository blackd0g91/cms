<?php

use App\Cms\Markdown;
use App\Cms\Widgets\RecipeAmountWidget;
use App\Models\Post;
use App\Models\Template;
use Tests\TestCase;

uses(TestCase::class);

function recipeWidget(string $markdown): string
{
    return trim(app(Markdown::class)->render($markdown));
}

test('servings show what a recipe makes, with buttons for the script to show', function () {
    $html = recipeWidget('{{ recipe-servings:4 }}');

    expect($html)->toContain('data-servings="4"')
        ->toContain('Serves')
        ->toContain('<output class="widget-servings-count" aria-live="polite">4</output>')
        ->toContain('aria-label="Fewer servings" hidden')
        ->toContain('aria-label="More servings" hidden');

    expect(recipeWidget('{{ recipe-servings:12 | <b>cookies</b> }}'))
        ->toContain('Makes')
        ->toContain('&lt;b&gt;cookies&lt;/b&gt;')
        ->not->toContain('<b>');
});

test('servings that are not a whole number from 1 to 999 are left as written', function (string $value) {
    expect(recipeWidget("{{ recipe-servings:{$value} }}"))->toContain('{{ recipe-servings:');
})->with(['0', 'four', '1000', '2.5', '-3']);

test('amounts are understood as written, with the style to scale them in', function (string $value, ?array $amount) {
    expect(RecipeAmountWidget::parse($value))->toBe($amount);
})->with([
    'grams' => ['200 g', ['from' => 200.0, 'to' => null, 'number' => '200', 'unit' => 'g', 'style' => 'decimal']],
    'no space' => ['200g', ['from' => 200.0, 'to' => null, 'number' => '200', 'unit' => 'g', 'style' => 'decimal']],
    'decimal comma' => ['1,5 kg', ['from' => 1.5, 'to' => null, 'number' => '1,5', 'unit' => 'kg', 'style' => 'comma']],
    'decimal point' => ['1.5 kg', ['from' => 1.5, 'to' => null, 'number' => '1.5', 'unit' => 'kg', 'style' => 'decimal']],
    'mixed number' => ['1 1/2 cups', ['from' => 1.5, 'to' => null, 'number' => '1 1/2', 'unit' => 'cups', 'style' => 'fraction']],
    'fraction' => ['1/2 cup', ['from' => 0.5, 'to' => null, 'number' => '1/2', 'unit' => 'cup', 'style' => 'fraction']],
    'fraction character' => ['½ tsp', ['from' => 0.5, 'to' => null, 'number' => '½', 'unit' => 'tsp', 'style' => 'fraction']],
    'whole and fraction character' => ['1½ cups', ['from' => 1.5, 'to' => null, 'number' => '1½', 'unit' => 'cups', 'style' => 'fraction']],
    'range' => ['2-3 eggs', ['from' => 2.0, 'to' => 3.0, 'number' => '2-3', 'unit' => 'eggs', 'style' => 'decimal']],
    'range with a dash' => ['2 – 3', ['from' => 2.0, 'to' => 3.0, 'number' => '2 – 3', 'unit' => '', 'style' => 'decimal']],
    'a count' => ['3', ['from' => 3.0, 'to' => null, 'number' => '3', 'unit' => '', 'style' => 'decimal']],
    'no number' => ['a pinch', null],
    'divided by zero' => ['1/0 cup', null],
    'backwards range' => ['3-2', null],
    'nothing' => ['', null],
]);

test('an amount shows as written, with its numbers for the script', function () {
    $html = recipeWidget('Add {{ recipe-amount:1 1/2 cups }} of <milk>.');

    expect($html)->toContain('data-amount="1.5" data-style="fraction"')
        ->toContain('<span class="widget-amount-value">1 1/2</span> <span class="widget-amount-unit">cups</span>');

    expect(recipeWidget('{{ recipe-amount:2-3 eggs }}'))->toContain('data-amount="2" data-amount-to="3"');
    expect(recipeWidget('{{ recipe-amount:a pinch }}'))->toContain('{{ recipe-amount:a pinch }}');
});

test('amounts stay in a post\'s plain text, while other widgets are left out', function () {
    $template = new Template(['fields' => [['handle' => 'method', 'label' => 'Method', 'type' => 'markdown', 'required' => false, 'options' => []]]]);
    $post = (new Post)->forceFill(['data' => ['method' => "{{ recipe-servings:4 }}\n\n- {{ recipe-amount:200 g }} flour\n- {{ recipe-amount:½ tsp }} salt\n\nWait {{ timer:10 }}."]]);
    $post->setRelation('template', $template);

    expect($post->plainText())->toBe('200 g flour ½ tsp salt Wait .');
});
