<?php

use App\Cms\TableOfContents;

test('h2 and h3 headings get ids and are listed in order', function () {
    $result = (new TableOfContents)->build('<h1>Title</h1><h2>Getting started</h2><p>x</p><h3>Install <code>git</code></h3><h4>Deep</h4><h2>Getting started</h2>');

    expect($result['headings'])->toBe([
        ['id' => 'getting-started', 'text' => 'Getting started', 'level' => 2],
        ['id' => 'install-git', 'text' => 'Install git', 'level' => 3],
        ['id' => 'getting-started-2', 'text' => 'Getting started', 'level' => 2],
    ])->and($result['html'])
        ->toContain('<h2 id="getting-started">Getting started</h2>')
        ->toContain('<h3 id="install-git">Install <code>git</code></h3>')
        ->toContain('<h2 id="getting-started-2">')
        ->toContain('<h1>Title</h1>')
        ->toContain('<h4>Deep</h4>');
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
