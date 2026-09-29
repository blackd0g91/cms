<?php

use App\Cms\ResponsiveImages;

test('html without uploaded images is left alone', function () {
    $html = '<p><img src="https://example.com/cat.png" alt="cat" /></p><p>text</p>';

    expect((new ResponsiveImages)->apply($html))->toBe($html);
});
