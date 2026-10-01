<?php

use App\Cms\Markdown;
use App\Cms\Widgets\TimerWidget;
use Tests\TestCase;

uses(TestCase::class);

test('durations are read in minutes or with units', function (string $written, ?int $seconds) {
    expect(TimerWidget::seconds($written))->toBe($seconds);
})->with([
    'minutes' => ['30', 1800],
    'half a minute' => ['0.5', 30],
    'seconds' => ['90s', 90],
    'minutes and seconds' => ['2m30s', 150],
    'hours and minutes' => ['1h30', 5400],
    'with spaces' => ['1h 30m', 5400],
    'min' => ['45min', 2700],
    'everything' => ['1h30m15s', 5415],
    'zero' => ['0', null],
    'over a day' => ['25h', null],
    'nonsense' => ['soon', null],
]);

test('times show like a kitchen timer', function () {
    expect(TimerWidget::format(90))->toBe('1:30')
        ->and(TimerWidget::format(1800))->toBe('30:00')
        ->and(TimerWidget::format(5415))->toBe('1:30:15');
});

test('a timer widget is a button with the time and an optional label', function () {
    $html = app(Markdown::class)->render('Boil for {{ timer:10 | Rest the dough }}.');

    expect($html)->toContain('data-timer="600" data-state="idle"')
        ->toContain('aria-label="Start Rest the dough"')
        ->toContain('<span class="widget-timer-time" role="timer">10:00</span>')
        ->toContain('<span class="widget-timer-label">Rest the dough</span>');

    expect(app(Markdown::class)->render('{{ timer:90s }}'))->toContain('aria-label="Start 1:30 timer"')->not->toContain('widget-timer-label');
});

test('labels are escaped and bad durations are left as written', function () {
    expect(app(Markdown::class)->render('{{ timer:5 | <b>x</b> }}'))->toContain('&lt;b&gt;x&lt;/b&gt;')->not->toContain('<b>')
        ->and(app(Markdown::class)->render('{{ timer:soon }}'))->toContain('{{ timer:soon }}');
});
