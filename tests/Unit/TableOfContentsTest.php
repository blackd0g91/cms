<?php

use App\Cms\TableOfContents;

test('h2 and h3 headings get ids and are listed in order', function () {
    $result = (new TableOfContents)->build('<h2>Getting started</h2><p>x</p><h3>Install <code>git</code></h3><h4>Deep</h4><h2>Getting started</h2>');

    expect($result['headings'])->toBe([
        ['id' => 'getting-started', 'text' => 'Getting started', 'level' => 2, 'depth' => 1],
        ['id' => 'install-git', 'text' => 'Install git', 'level' => 3, 'depth' => 2],
        ['id' => 'getting-started-2', 'text' => 'Getting started', 'level' => 2, 'depth' => 1],
    ])->and($result['html'])
        ->toContain('<h2 id="getting-started">Getting started</h2>')
        ->toContain('<h3 id="install-git">Install <code>git</code></h3>')
        ->toContain('<h2 id="getting-started-2">')
        ->toContain('<h4>Deep</h4>');
});

test('posts whose sections start at h1, like # in markdown, list h1 and h2', function () {
    $result = (new TableOfContents)->build('<h1>Setup</h1><h2>Install</h2><h3>Deep</h3><h1>Usage</h1>');

    expect($result['headings'])->toBe([
        ['id' => 'setup', 'text' => 'Setup', 'level' => 1, 'depth' => 1],
        ['id' => 'install', 'text' => 'Install', 'level' => 2, 'depth' => 2],
        ['id' => 'usage', 'text' => 'Usage', 'level' => 1, 'depth' => 1],
    ])->and($result['html'])
        ->toContain('<h1 id="setup">Setup</h1>')
        ->toContain('<h3>Deep</h3>');
});

test('a single title above the sections, like # Title in markdown, is left out', function () {
    $result = (new TableOfContents)->build('<h1>My notes</h1><h2>Setup</h2><h3>Install</h3><h2>Usage</h2>');

    expect(array_column($result['headings'], 'depth', 'text'))->toBe(['Setup' => 1, 'Install' => 2, 'Usage' => 1])
        ->and($result['html'])->toContain('<h1>My notes</h1>');
});

test('a title and one section list only the section, while a section and a subsection list both', function () {
    expect(array_column((new TableOfContents)->build('<h1>Title</h1><h2>Only one</h2>')['headings'], 'text'))
        ->toBe(['Only one'])
        ->and(array_column((new TableOfContents)->build('<h2>Section</h2><h3>Sub</h3>')['headings'], 'depth', 'text'))
        ->toBe(['Section' => 1, 'Sub' => 2]);
});

test('the top level is the highest one with text, whatever it is', function () {
    $result = (new TableOfContents)->build('<h2> </h2><h3>Three</h3><h4>Four</h4><h5>Five</h5>');

    expect(array_column($result['headings'], 'depth', 'text'))->toBe(['Three' => 1, 'Four' => 2]);
});

test('without headings there is nothing to list', function () {
    expect((new TableOfContents)->build('<p>Just text</p>'))->toBe(['html' => '<p>Just text</p>', 'headings' => []]);
});

test('existing ids and attributes are kept', function () {
    $result = (new TableOfContents)->build('<h2 class="big" id="custom">Custom</h2><h2 class="x">Other</h2>');

    expect($result['headings'][0]['id'])->toBe('custom')
        ->and($result['html'])->toContain('<h2 class="big" id="custom">Custom</h2>')
        ->and($result['html'])->toContain('<h2 class="x" id="other">Other</h2>');
});

test('empty headings and headings without letters are handled', function () {
    $result = (new TableOfContents)->build('<h2></h2><h2>!!!</h2><h2>Pão &amp; queijo</h2>');

    expect(array_column($result['headings'], 'id'))->toBe(['section', 'pao-queijo'])
        ->and($result['headings'][1]['text'])->toBe('Pão & queijo');
});
