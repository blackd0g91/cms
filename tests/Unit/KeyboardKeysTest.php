<?php

use App\Cms\Markdown;
use Tests\TestCase;

uses(TestCase::class);

function keys(string $markdown): string
{
    return trim(app(Markdown::class)->render($markdown));
}

test('keys and combinations', function () {
    expect(keys('Press [[Ctrl]]+[[C]].'))->toBe('<p>Press <kbd>Ctrl</kbd>+<kbd>C</kbd>.</p>')
        ->and(keys('[[Ctrl+Shift+P]]'))->toBe('<p><span class="keys"><kbd>Ctrl</kbd><span class="keys-plus">+</span><kbd>Shift</kbd><span class="keys-plus">+</span><kbd>P</kbd></span></p>')
        ->and(keys('[[ Esc ]]'))->toBe('<p><kbd>Esc</kbd></p>');
});

test('the plus key itself', function () {
    expect(keys('[[+]]'))->toBe('<p><kbd>+</kbd></p>')
        ->and(keys('[[Ctrl++]]'))->toContain('<kbd>Ctrl</kbd><span class="keys-plus">+</span><kbd>+</kbd>');
});

test('code, empty brackets and html are handled safely', function () {
    expect(keys('`[[Ctrl]]`'))->toBe('<p><code>[[Ctrl]]</code></p>')
        ->and(keys('[[]] and [[ ]]'))->toBe('<p>[[]] and [[ ]]</p>')
        ->and(keys('[[<b>x</b>]]'))->toBe('<p><kbd>&lt;b&gt;x&lt;/b&gt;</kbd></p>');
});
