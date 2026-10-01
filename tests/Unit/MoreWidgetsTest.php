<?php

use App\Cms\Markdown;
use App\Cms\Widgets\CountdownWidget;
use App\Cms\Widgets\YouTubeWidget;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Tests\TestCase;

uses(TestCase::class);

function widget(string $markdown): string
{
    return trim(app(Markdown::class)->render($markdown));
}

test('youtube links and ids are understood, with start times', function (string $address, ?array $video) {
    expect(YouTubeWidget::parse($address))->toBe($video);
})->with([
    'watch link' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ', ['id' => 'dQw4w9WgXcQ', 'start' => 0]],
    'short link' => ['https://youtu.be/dQw4w9WgXcQ', ['id' => 'dQw4w9WgXcQ', 'start' => 0]],
    'with a time' => ['https://youtu.be/dQw4w9WgXcQ?t=90', ['id' => 'dQw4w9WgXcQ', 'start' => 90]],
    'with a written time' => ['https://www.youtube.com/watch?v=dQw4w9WgXcQ&t=1m30s', ['id' => 'dQw4w9WgXcQ', 'start' => 90]],
    'shorts' => ['https://www.youtube.com/shorts/dQw4w9WgXcQ', ['id' => 'dQw4w9WgXcQ', 'start' => 0]],
    'embed' => ['https://www.youtube.com/embed/dQw4w9WgXcQ', ['id' => 'dQw4w9WgXcQ', 'start' => 0]],
    'just the id' => ['dQw4w9WgXcQ', ['id' => 'dQw4w9WgXcQ', 'start' => 0]],
    'another site' => ['https://vimeo.com/123456', null],
    'not an id' => ['hello', null],
]);

test('a youtube widget loads nothing from youtube, and links to the video', function () {
    $html = widget('{{ youtube:https://youtu.be/dQw4w9WgXcQ?t=90 | Our trip }}');

    expect($html)->toContain('data-youtube="dQw4w9WgXcQ" data-start="90"')
        ->toContain('href="https://www.youtube.com/watch?v=dQw4w9WgXcQ&amp;t=90s"')
        ->toContain('Our trip')
        ->not->toContain('<iframe')
        ->not->toContain('<img');
});

test('countdowns describe how long until or since a date', function (string $target, bool $dateOnly, string $text) {
    $now = CarbonImmutable::parse('2026-10-01 12:00');

    expect(CountdownWidget::describe(CarbonImmutable::parse($target), $dateOnly, $now))->toBe($text);
})->with([
    'days away' => ['2026-12-25', true, '85 days to go'],
    'tomorrow' => ['2026-10-02', true, 'Tomorrow'],
    'today' => ['2026-10-01', true, 'Today!'],
    'yesterday' => ['2026-09-30', true, 'Yesterday'],
    'days ago' => ['2026-09-28', true, '3 days ago'],
    'hours away' => ['2026-10-01 17:30', false, '5 h 30 min to go'],
    'a day and hours' => ['2026-10-02 15:00', false, '1 day 3 h to go'],
    'minutes ago' => ['2026-10-01 11:20', false, '40 min ago'],
    'now' => ['2026-10-01 12:00', false, 'Now!'],
]);

test('a countdown widget carries its date for the page to update', function () {
    Date::setTestNow('2026-10-01 12:00');

    expect(widget('{{ countdown:2026-12-25 | Christmas }}'))
        ->toContain('data-countdown="2026-12-25T00:00" data-date-only')
        ->toContain('<span class="widget-countdown-label">Christmas</span>')
        ->toContain('85 days to go')
        ->and(widget('{{ countdown:2026-12-25 18:00 }}'))->toContain('data-countdown="2026-12-25T18:00"')->not->toContain('data-date-only');
});

test('impossible dates are left as written', function (string $value) {
    expect(widget("{{ countdown:{$value} }}"))->toContain("{{ countdown:{$value} }}");
})->with(['2026-02-31', 'soon', '25/12/2026']);

test('spoilers hide their text until shown, escaped', function () {
    expect(widget('{{ spoiler:It was <b>him</b> }}'))
        ->toContain('aria-expanded="false"')
        ->toContain('<span class="widget-spoiler-text">It was &lt;b&gt;him&lt;/b&gt;</span>');
});

test('stopwatches work with or without a label', function () {
    expect(widget('{{ stopwatch }}'))->toContain('aria-label="Start stopwatch"')->toContain('0:00.0')->not->toContain('widget-stopwatch-label')
        ->and(widget('{{ stopwatch:Rest timer }}'))->toContain('aria-label="Start Rest timer"')->toContain('<span class="widget-stopwatch-label">Rest timer</span>');
});

test('widgets never put lists inside paragraphs', function () {
    expect(widget('Time it: {{ stopwatch }}'))->not->toContain('<ol')->not->toContain('<ul');
});
