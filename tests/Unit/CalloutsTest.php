<?php

use App\Cms\Markdown;
use Tests\TestCase;

uses(TestCase::class);

function callout(string $markdown): string
{
    return preg_replace('/<svg.*?<\/svg>/s', '[icon]', app(Markdown::class)->render($markdown));
}

test('a marked quote becomes a callout titled after its type', function () {
    expect(callout("> [!TIP]\n> Salt the water."))->toBe(
        '<div class="callout callout-tip" role="note"><p class="callout-title">[icon]<span>Tip</span></p><div class="callout-body"><p>Salt the water.</p></div></div>'."\n",
    );
});

test('every type works, in any case, with a custom title that keeps its formatting', function (string $type) {
    expect(callout("> [!{$type}] Hot *oil*\n> Careful."))
        ->toContain('callout callout-'.strtolower($type))
        ->toContain('<span>Hot <em>oil</em></span>')
        ->toContain('<p>Careful.</p>');
})->with(['NOTE', 'tip', 'Important', 'WARNING', 'caution']);

test('callouts can be collapsible, closed or open', function () {
    expect(callout("> [!NOTE]- Why\n> Starch."))->toContain('<details class="callout callout-note"><summary class="callout-title">')
        ->and(callout("> [!NOTE]+ Why\n> Starch."))->toContain('<details class="callout callout-note" open>')
        ->and(callout("> [!DETAILS] More\n> Text."))->toContain('<details class="callout callout-details">')
        ->and(callout("> [!DETAILS]+ More\n> Text."))->toContain('<details class="callout callout-details" open>');
});

test('callouts keep whatever is inside', function () {
    $html = callout("> [!WARNING]\n> Steps:\n>\n> - one\n> - two\n>\n> ```bash\n> rm -rf\n> ```");

    expect($html)->toContain('<p>Steps:</p>')->toContain('<li>one</li>')->toContain('class="phiki')->toContain('>rm<');
});

test('other quotes are left as quotes', function () {
    expect(callout('> Just a quote.'))->toContain('<blockquote>')
        ->and(callout('> [!UNKNOWN] Not a type.'))->toContain('<blockquote>')->toContain('[!UNKNOWN]')
        ->and(callout('> Text with [!TIP] later.'))->toContain('<blockquote>');
});
