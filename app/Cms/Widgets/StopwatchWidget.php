<?php

namespace App\Cms\Widgets;

/**
 * {{ stopwatch }} or {{ stopwatch:Rest timer }}: start and pause it, take
 * laps and reset it (resources/js/site.ts), for timing steps that take as
 * long as they take.
 */
class StopwatchWidget implements Widget
{
    public function name(): string
    {
        return 'stopwatch';
    }

    public function render(string $value): ?string
    {
        $label = trim($value);

        if (mb_strlen($label) > 100) {
            return null;
        }

        $name = $label !== '' ? $label : 'stopwatch';

        return '<span class="widget widget-stopwatch" data-state="idle">'
            .'<span class="widget-stopwatch-controls">'
            .'<button type="button" class="widget-stopwatch-main" aria-label="Start '.e($name).'">'
            .'<svg class="widget-stopwatch-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13a7 7 0 1 0 14 0a7 7 0 0 0 -14 0z"/><path d="M14.5 10.5l-2.5 2.5"/><path d="M17 8l1 -1"/><path d="M14 3h-4"/></svg>'
            .'<span class="widget-stopwatch-time" role="timer">0:00.0</span>'
            .($label !== '' ? '<span class="widget-stopwatch-label">'.e($label).'</span>' : '')
            .'</button>'
            .'<button type="button" class="widget-stopwatch-lap" hidden>Lap</button>'
            .'<button type="button" class="widget-stopwatch-reset" aria-label="Reset '.e($name).'" hidden>↺</button>'
            .'</span>'
            .'<span class="widget-stopwatch-laps" role="list" hidden></span>'
            .'</span>';
    }
}
