import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { format, startTimers } from '@/widgets/timer';

describe('format', () => {
    it('writes minutes and seconds, and hours when there are any', () => {
        expect(format(0)).toBe('0:00');
        expect(format(65)).toBe('1:05');
        expect(format(59.2)).toBe('1:00');
        expect(format(3725)).toBe('1:02:05');
        expect(format(-3)).toBe('0:00');
    });
});

// Just enough of the Web Audio API to schedule (silent) beeps.
class FakeAudioContext {
    currentTime = 0;
    destination = {};
    resume = () => Promise.resolve();
    createGain = () => ({
        gain: { setValueAtTime() {}, linearRampToValueAtTime() {} },
        connect: (node: unknown) => node,
    });
    createOscillator = () => ({
        frequency: { value: 0 },
        connect: (node: unknown) => node,
        start() {},
        stop() {},
        disconnect() {},
    });
}

describe('startTimers', () => {
    let stop = () => {};

    beforeEach(() => {
        vi.useFakeTimers();
        vi.stubGlobal('AudioContext', FakeAudioContext);
        document.title = 'Soup';
        // As app/Cms/Widgets/TimerWidget.php writes it.
        document.body.innerHTML =
            '<span class="widget widget-timer" data-timer="90" data-state="idle">' +
            '<button type="button" class="widget-timer-main" aria-label="Start Rice"><span class="widget-timer-time" role="timer">1:30</span><span class="widget-timer-label">Rice</span></button>' +
            '<button type="button" class="widget-timer-reset" aria-label="Reset Rice" hidden>↺</button>' +
            '</span>';
        stop = startTimers(document);
    });

    afterEach(() => {
        stop();
        vi.useRealTimers();
        document.body.innerHTML = '';
    });

    const widget = () => document.querySelector<HTMLElement>('.widget-timer')!;
    const main = () =>
        document.querySelector<HTMLButtonElement>('.widget-timer-main')!;
    const time = () =>
        document.querySelector('.widget-timer-time')!.textContent;

    it('counts down, pauses and resumes', () => {
        main().click();

        expect(widget().dataset.state).toBe('running');
        expect(main().getAttribute('aria-label')).toBe('Pause Rice');

        vi.advanceTimersByTime(30_000);
        expect(time()).toBe('1:00');

        main().click();
        expect(widget().dataset.state).toBe('paused');

        vi.advanceTimersByTime(30_000);
        expect(time()).toBe('1:00');

        main().click();
        vi.advanceTimersByTime(10_000);
        expect(time()).toBe('0:50');
    });

    it('rings when done, with the alarm in the page title, until stopped', () => {
        main().click();
        vi.advanceTimersByTime(91_000);

        expect(widget().dataset.state).toBe('ringing');
        expect(time()).toBe("Time's up!");
        expect(document.title).toBe('⏰ Rice - Soup');

        main().click();

        expect(widget().dataset.state).toBe('idle');
        expect(time()).toBe('1:30');
        expect(document.title).toBe('Soup');
    });

    it('resets to the full time', () => {
        const reset = document.querySelector<HTMLButtonElement>(
            '.widget-timer-reset',
        )!;
        main().click();

        expect(reset.hidden).toBe(false);

        vi.advanceTimersByTime(20_000);
        reset.click();

        expect(widget().dataset.state).toBe('idle');
        expect(time()).toBe('1:30');
        expect(reset.hidden).toBe(true);
    });

    it('stops ticking when the widget is stopped', () => {
        main().click();
        stop();

        expect(widget().dataset.state).toBe('idle');

        vi.advanceTimersByTime(120_000);

        expect(widget().dataset.state).toBe('idle');
    });
});
