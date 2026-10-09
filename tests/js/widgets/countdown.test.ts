import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { describe as describeTime, startCountdowns } from '@/widgets/countdown';

const now = new Date(2026, 9, 9, 12, 0);

describe('describe', () => {
    it('counts whole days for a date', () => {
        expect(describeTime(new Date(2026, 9, 9), true, now)).toBe('Today!');
        expect(describeTime(new Date(2026, 9, 10), true, now)).toBe('Tomorrow');
        expect(describeTime(new Date(2026, 9, 8), true, now)).toBe('Yesterday');
        expect(describeTime(new Date(2026, 9, 19), true, now)).toBe(
            '10 days to go',
        );
        expect(describeTime(new Date(2026, 9, 1), true, now)).toBe(
            '8 days ago',
        );
    });

    it('gets more precise as a moment comes closer', () => {
        expect(describeTime(new Date(2026, 9, 12, 12, 0), false, now)).toBe(
            '3 days to go',
        );
        expect(describeTime(new Date(2026, 9, 10, 15, 0), false, now)).toBe(
            '1 day 3 h to go',
        );
        expect(describeTime(new Date(2026, 9, 9, 14, 30), false, now)).toBe(
            '2 h 30 min to go',
        );
        expect(describeTime(new Date(2026, 9, 9, 12, 5), false, now)).toBe(
            '5 min to go',
        );
        expect(describeTime(new Date(2026, 9, 9, 12, 0, 30), false, now)).toBe(
            'Now!',
        );
        expect(describeTime(new Date(2026, 9, 9, 11, 0), false, now)).toBe(
            '1 h 0 min ago',
        );
    });
});

describe('startCountdowns', () => {
    afterEach(() => {
        vi.useRealTimers();
        document.body.innerHTML = '';
    });

    it('keeps the countdown up to date', () => {
        vi.useFakeTimers();
        vi.setSystemTime(now);
        document.body.innerHTML =
            '<span class="widget widget-countdown" data-countdown="2026-10-09T12:10"><span class="widget-countdown-value">…</span></span>';

        const stop = startCountdowns(document);
        const value = document.querySelector('.widget-countdown-value')!;

        expect(value.textContent).toBe('10 min to go');

        vi.advanceTimersByTime(5 * 60_000);

        expect(value.textContent).toBe('5 min to go');

        stop();
        vi.advanceTimersByTime(5 * 60_000);

        expect(value.textContent).toBe('5 min to go');
    });
});
