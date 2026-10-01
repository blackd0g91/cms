<?php

use App\Cms\Markdown;
use Tests\TestCase;

uses(TestCase::class);

function md(string $markdown): string
{
    return app(Markdown::class)->render($markdown);
}

test('shortcodes become emoji', function () {
    expect(md('Pizza :pizza: night :tada:'))->toContain('Pizza 🍕 night 🎉')
        ->and(md(':+1: and :thumbsup:'))->toContain('👍️ and 👍️')
        ->and(md('**hot :fire:** [hi :smile:](https://example.com)'))->toContain('<strong>hot 🔥</strong>')->toContain('hi 😄</a>')
        ->and(md(':Pizza:'))->toContain('🍕');
});

test('unknown names, times and code are left alone', function () {
    expect(md('at 10:30:45 and :not_a_real_emoji:'))->toContain('at 10:30:45 and :not_a_real_emoji:')
        ->and(md('`:pizza:`'))->toContain('<code>:pizza:</code>')
        ->and(md("```\n:pizza:\n```"))->toContain(':pizza:')->not->toContain('🍕');
});
