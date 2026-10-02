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

test('html is shown as typed, as text', function (string $markdown, string $html) {
    expect(trim(render($markdown)))->toBe($html);
})->with([
    'inline tags' => ['Some <b>bold</b> words', '<p>Some &lt;b&gt;bold&lt;/b&gt; words</p>'],
    'a line break' => ['One<br>two', '<p>One&lt;br&gt;two</p>'],
    'event handlers' => ['A <img src="x.png" onerror="alert(1)"> here', '<p>A &lt;img src="x.png" onerror="alert(1)"&gt; here</p>'],
    'blocks, as paragraphs with their lines' => ["<script>\nalert(1)\n</script>", "<p>&lt;script&gt;<br>\nalert(1)<br>\n&lt;/script&gt;</p>"],
    'comments' => ["<!-- a note -->\n\nText", "<p>&lt;!-- a note --&gt;</p>\n<p>Text</p>"],
    'entities as typed' => ["<div>\n&amp; &copy;\n</div>", "<p>&lt;div&gt;<br>\n&amp;amp; &amp;copy;<br>\n&lt;/div&gt;</p>"],
]);

test('links in angle brackets and widgets still work without html', function () {
    expect(render('<https://example.com> and <me@example.com>'))
        ->toContain('<a href="https://example.com">https://example.com</a>')
        ->toContain('<a href="mailto:me@example.com">me@example.com</a>');

    expect(render('Copy {{ copy:<b>it</b> }} <b>now</b>'))
        ->toContain('class="widget widget-copy"')
        ->toContain('&lt;b&gt;now&lt;/b&gt;')
        ->not->toContain('<b>');
});
