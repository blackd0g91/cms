<?php

namespace App\Cms\Widgets;

/**
 * {{ timer:30 }} (minutes), {{ timer:1h30 }}, {{ timer:90s }} or
 * {{ timer:10 | Rest the dough }}: a countdown that rings until stopped. The
 * page's script (resources/js/site.ts) makes it work; without it, the time
 * still shows.
 */
class TimerWidget implements ReadsAsText, Widget
{
    private const int MAX_SECONDS = 24 * 3600;

    public function name(): string
    {
        return 'timer';
    }

    public function text(string $value): ?string
    {
        $seconds = self::seconds(trim(explode('|', $value, 2)[0]));

        return $seconds === null ? null : self::format($seconds);
    }

    public function render(string $value): ?string
    {
        [$duration, $label] = array_pad(array_map('trim', explode('|', $value, 2)), 2, '');
        $seconds = self::seconds($duration);

        if ($seconds === null) {
            return null;
        }

        $time = self::format($seconds);
        $name = $label !== '' ? $label : "{$time} timer";

        return '<span class="widget widget-timer" data-timer="'.$seconds.'" data-state="idle">'
            .'<button type="button" class="widget-timer-main" aria-label="Start '.e($name).'">'
            .'<svg class="widget-timer-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 13m-7 0a7 7 0 1 0 14 0a7 7 0 1 0 -14 0"/><path d="M12 10v3h2"/><path d="M7 4l-2.75 2"/><path d="M17 4l2.75 2"/></svg>'
            .'<span class="widget-timer-time" role="timer">'.$time.'</span>'
            .($label !== '' ? '<span class="widget-timer-label">'.e($label).'</span>' : '')
            .'</button>'
            .'<button type="button" class="widget-timer-reset" aria-label="Reset '.e($name).'" hidden>↺</button>'
            .'</span>';
    }

    /**
     * "30" (minutes), "90s", "2m30s", "1h30" or "1h 30m" in seconds, or null.
     */
    public static function seconds(string $duration): ?int
    {
        $duration = strtolower(str_replace(' ', '', $duration));

        if (preg_match('/^\d+(\.\d+)?$/', $duration)) {
            $seconds = (int) round((float) $duration * 60);
        } elseif (preg_match('/^(?:(\d+)h)?(?:(\d+)m(?:in)?)?(?:(\d+)s)?$/', $duration, $match) && $duration !== '') {
            $seconds = (int) ($match[1] ?? 0) * 3600 + (int) ($match[2] ?? 0) * 60 + (int) ($match[3] ?? 0);
        } elseif (preg_match('/^(\d+)h(\d+)$/', $duration, $match)) {
            // "1h30": the number after the hours is minutes.
            $seconds = (int) $match[1] * 3600 + (int) $match[2] * 60;
        } else {
            return null;
        }

        return $seconds > 0 && $seconds <= self::MAX_SECONDS ? $seconds : null;
    }

    /**
     * 1:30:00, 25:00 or 0:45, as on a kitchen timer.
     */
    public static function format(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $rest = $seconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $rest)
            : sprintf('%d:%02d', $minutes, $rest);
    }
}
