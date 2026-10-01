<?php

use App\Cms\Markdown;
use Tests\TestCase;

uses(TestCase::class);

function widgets(string $markdown): string
{
    return trim(app(Markdown::class)->render($markdown));
}

test('a qr widget draws a qr code with its text as the caption', function () {
    $html = widgets('Scan {{ qr:https://example.com }} now');

    expect($html)->toContain('<span class="widget widget-qr"><svg aria-hidden="true"')
        ->toContain('<span class="widget-qr-caption">https://example.com</span>')
        ->not->toContain('<?xml');
});

test('a caption can be given after a bar', function () {
    expect(widgets('{{ qr:WIFI:S:Home;T:WPA;P:secret;; | Home Wi-Fi }}'))
        ->toContain('<span class="widget-qr-caption">Home Wi-Fi</span>')
        ->not->toContain('secret;;</span>');
});

test('captions and values are escaped', function () {
    $html = widgets('{{ qr:x | <img src=x onerror=alert(1)> }}');

    expect($html)->toContain('&lt;img src=x onerror=alert(1)&gt;')->not->toContain('<img');
});

test('unknown widgets, missing values and code are left as written', function () {
    expect(widgets('{{ nope:1 }} {{ qr }} {{ qr: }}'))->toBe('<p>{{ nope:1 }} {{ qr }} {{ qr: }}</p>')
        ->and(widgets('`{{ qr:x }}`'))->toBe('<p><code>{{ qr:x }}</code></p>');
});
