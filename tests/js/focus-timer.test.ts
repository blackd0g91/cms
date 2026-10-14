import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { clock, startFocusTimer } from '@/focus-timer';

describe('focus timer', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-10-14T15:00:00'));
        localStorage.clear();
        document.body.innerHTML = `
            <section data-focus-timer hidden>
                <span data-timer-time></span><span data-timer-mode></span><span data-timer-state></span>
                <button data-timer-toggle><span data-timer-toggle-label></span></button>
            </section>
            <div data-focus-timer-panel>
                <button data-timer-choose="focus"></button><button data-timer-choose="short"></button>
                <button data-timer-reset></button><span data-timer-sessions></span>
            </div>`;
    });

    afterEach(() => {
        vi.useRealTimers();
        document.body.innerHTML = '';
        localStorage.clear();
    });

    const text = (selector: string) =>
        document.querySelector(selector)!.textContent;
    const click = (selector: string) =>
        document.querySelector<HTMLButtonElement>(selector)!.click();

    it('shows itself, ready for 25 minutes of focus', () => {
        startFocusTimer();

        expect(
            document.querySelector<HTMLElement>('[data-focus-timer]')!.hidden,
        ).toBe(false);
        expect(text('[data-timer-time]')).toBe('25:00');
        expect(text('[data-timer-mode]')).toBe('Focus');
        expect(text('[data-timer-state]')).toBe('Ready');
    });

    it('counts down, pauses and resets', () => {
        startFocusTimer();

        click('[data-timer-toggle]');
        vi.advanceTimersByTime(61_000);
        expect(text('[data-timer-time]')).toBe('23:59');
        expect(text('[data-timer-toggle-label]')).toBe('Pause');

        click('[data-timer-toggle]');
        vi.advanceTimersByTime(60_000);
        expect(text('[data-timer-time]')).toBe('23:59');
        expect(text('[data-timer-state]')).toBe('Paused');

        click('[data-timer-reset]');
        expect(text('[data-timer-time]')).toBe('25:00');
    });

    it('goes on after a reload', () => {
        startFocusTimer();
        click('[data-timer-toggle]');
        vi.advanceTimersByTime(5 * 60_000);

        startFocusTimer();
        expect(text('[data-timer-time]')).toBe('20:00');
        expect(text('[data-timer-state]')).toBe('Running');
    });

    it('says when focus is done, counts it and offers the break', () => {
        startFocusTimer();
        click('[data-timer-toggle]');
        vi.advanceTimersByTime(25 * 60_000 + 500);

        expect(document.querySelector('.toast')!.textContent).toContain(
            'Time for a short break',
        );
        expect(text('[data-timer-mode]')).toBe('Short break');
        expect(text('[data-timer-time]')).toBe('05:00');
        expect(text('[data-timer-sessions]')).toBe('1');

        document
            .querySelector<HTMLButtonElement>('.toast .toast-primary')!
            .click();
        expect(text('[data-timer-state]')).toBe('Running');
        expect(document.querySelector('.toast')).toBeNull();
    });

    it('switches between focus and breaks', () => {
        startFocusTimer();

        click('[data-timer-choose="short"]');

        expect(text('[data-timer-time]')).toBe('05:00');
        expect(
            document
                .querySelector('[data-timer-choose="short"]')!
                .getAttribute('aria-pressed'),
        ).toBe('true');
    });

    it('writes the time as minutes and seconds', () => {
        expect(clock(25 * 60_000)).toBe('25:00');
        expect(clock(59_001)).toBe('01:00');
        expect(clock(0)).toBe('00:00');
    });
});
