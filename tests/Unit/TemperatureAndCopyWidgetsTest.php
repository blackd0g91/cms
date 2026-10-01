<?php

use App\Cms\Markdown;
use App\Cms\Widgets\TemperatureWidget;
use Tests\TestCase;

uses(TestCase::class);

function temperature(string $value): ?string
{
    $html = (new TemperatureWidget)->render($value);

    return $html === null ? null : html_entity_decode(strip_tags($html));
}

test('temperatures show both units, written one exact and the other rounded like recipes do', function (string $written, string $shown) {
    expect(temperature($written))->toBe($shown);
})->with([
    'oven, celsius' => ['180c', '180 °C/355 °F'],
    'oven, fahrenheit' => ['350f', '175 °C/350 °F'],
    'with a degree sign and space' => ['220 °C', '220 °C/430 °F'],
    'meat stays exact' => ['63c', '63 °C/145 °F'],
    'meat, fahrenheit' => ['165F', '74 °C/165 °F'],
    'decimals' => ['37.5c', '37.5 °C/100 °F'],
    'comma decimals' => ['37,5c', '37.5 °C/100 °F'],
    'freezer' => ['-18c', '-18 °C/0 °F'],
]);

test('the written unit comes first', function () {
    expect((new TemperatureWidget)->render('180c'))->toContain('data-first="c"')
        ->and((new TemperatureWidget)->render('350f'))->toContain('data-first="f"');
});

test('nonsense and impossible temperatures are left as written', function (string $value) {
    expect(temperature($value))->toBeNull();
})->with(['hot', '180', '180k', '2000c', '-300c']);

test('copy widgets show the text, or the label instead of it', function () {
    $plain = app(Markdown::class)->render('{{ copy:npm run dev }}');
    $labelled = app(Markdown::class)->render('{{ copy:hunter2 | Wi-Fi password }}');

    expect($plain)->toContain('data-copy="npm run dev"')->toContain('<code class="widget-copy-text">npm run dev</code>')
        ->and($labelled)->toContain('data-copy="hunter2"')->toContain('Wi-Fi password')
        ->and(strip_tags($labelled))->not->toContain('hunter2');
});

test('copied text is escaped', function () {
    expect(app(Markdown::class)->render('{{ copy:"a" & <b> }}'))
        ->toContain('data-copy="&quot;a&quot; &amp; &lt;b&gt;"')
        ->not->toContain('<b>');
});
