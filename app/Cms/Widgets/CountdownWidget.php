<?php

namespace App\Cms\Widgets;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * {{ countdown:2026-12-25 }}, {{ countdown:2026-12-25 18:00 | Christmas }}:
 * how long until a date ("84 days to go"), or since it ("3 days ago"). The
 * page's script keeps it up to date in the reader's own time zone; the text
 * written here is what shows without it.
 */
class CountdownWidget implements ReadsAsText, Widget
{
    public function name(): string
    {
        return 'countdown';
    }

    public function text(string $value): ?string
    {
        [$when, $label] = array_pad(array_map('trim', explode('|', $value, 2)), 2, '');

        if ($this->render($value) === null) {
            return null;
        }

        // Not how long is left, which would go out of date.
        return $label !== '' ? $label : CarbonImmutable::parse(substr($when, 0, 10))->format('M j, Y');
    }

    public function render(string $value): ?string
    {
        [$when, $label] = array_pad(array_map('trim', explode('|', $value, 2)), 2, '');

        if (! preg_match('/^(\d{4}-\d{2}-\d{2})(?:[ T](\d{1,2}:\d{2}))?$/', $when, $match)) {
            return null;
        }

        try {
            $target = CarbonImmutable::createFromFormat('Y-m-d H:i', $match[1].' '.($match[2] ?? '00:00'));
        } catch (Throwable) {
            return null;
        }

        // A date like 2026-02-31 rolls over to March; refuse it instead.
        if (! $target || $target->format('Y-m-d') !== $match[1]) {
            return null;
        }

        $dateOnly = ! isset($match[2]);

        return '<span class="widget widget-countdown" data-countdown="'.$target->format('Y-m-d\TH:i').'"'.($dateOnly ? ' data-date-only' : '').'>'
            .($label !== '' ? '<span class="widget-countdown-label">'.e($label).'</span>' : '')
            .'<span class="widget-countdown-value">'.e(self::describe($target, $dateOnly, now()->toImmutable())).'</span>'
            .'</span>';
    }

    /**
     * Matches the wording of resources/js/site.ts.
     */
    public static function describe(CarbonImmutable $target, bool $dateOnly, CarbonImmutable $now): string
    {
        if ($dateOnly) {
            $days = (int) $now->startOfDay()->diffInDays($target->startOfDay(), false);

            return match (true) {
                $days === 0 => 'Today!',
                $days === 1 => 'Tomorrow',
                $days === -1 => 'Yesterday',
                $days > 1 => "{$days} days to go",
                default => abs($days).' days ago',
            };
        }

        $minutes = (int) floor($now->diffInMinutes($target, false));
        $future = $minutes >= 0;
        $minutes = abs($minutes);
        $days = intdiv($minutes, 1440);
        $hours = intdiv($minutes % 1440, 60);
        $rest = $minutes % 60;

        $span = match (true) {
            $days >= 2 => "{$days} days",
            $days === 1 => "1 day {$hours} h",
            $hours > 0 => "{$hours} h {$rest} min",
            $minutes > 0 => "{$minutes} min",
            default => null,
        };

        return match (true) {
            $span === null => 'Now!',
            $future => "{$span} to go",
            default => "{$span} ago",
        };
    }
}
