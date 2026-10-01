<?php

use App\Cms\Markdown;
use App\Cms\TableOfContents;
use Tests\TestCase;

uses(TestCase::class);

function render(string $markdown): string
{
    return app(Markdown::class)->render($markdown);
}

test('footnotes link both ways', function () {
    $html = render("A note[^1].\n\n[^1]: The note.");

    expect($html)->toContain('href="#fn:1"')
        ->toContain('id="fn:1"')
        ->toContain('href="#fnref:1"')
        ->toContain('The note.&nbsp;<a class="footnote-backref"')
        ->toContain("\u{21A9}\u{FE0E}");
});

test('definition lists', function () {
    expect(render("Term\n: One\n: Two"))->toContain('<dl>')->toContain('<dt>Term</dt>')->toContain('<dd>One</dd>')->toContain('<dd>Two</dd>');
});

test('highlight, subscript and superscript', function () {
    expect(render('==hot== H~2~O x^2^'))->toContain('<mark>hot</mark> H<sub>2</sub>O x<sup>2</sup>');
});

test('tildes and carets with spaces stay as written, and double tildes still strike through', function () {
    expect(render('About ~10 minutes, or ~5 to ~10. 5 ^ 2 is ^ big'))
        ->toContain('About ~10 minutes, or ~5 to ~10. 5 ^ 2 is ^ big')
        ->and(render('~~old~~'))->toContain('<del>old</del>');
});

test('code is not touched', function () {
    expect(render('`H~2~O ==x== x^2^`'))->toContain('<code>H~2~O ==x== x^2^</code>');
});

test('headings can have their own id and class, but not event handlers or styles', function () {
    $html = render('## Setup {#install .big onclick="alert(1)" style="color:red"}');

    expect($html)->toContain('<h2 class="big" id="install">Setup</h2>')
        ->not->toContain('onclick')
        ->not->toContain('style');
});

test('the table of contents uses custom heading ids', function () {
    $result = app(TableOfContents::class)->build(render("## Setup {#install}\n\n## Use"));

    expect(array_column($result['headings'], 'id'))->toBe(['install', 'use']);
});
