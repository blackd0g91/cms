import { eachWidget } from './each';

/* {{ stopwatch }}: start and pause, laps and reset. */

export const format = (ms: number) => {
    const tenths = Math.floor(ms / 100);
    const hours = Math.floor(tenths / 36_000);
    const minutes = Math.floor((tenths % 36_000) / 600);
    const seconds = String(Math.floor((tenths % 600) / 10)).padStart(2, '0');
    const shown = `${seconds}.${tenths % 10}`;

    return hours
        ? `${hours}:${String(minutes).padStart(2, '0')}:${shown}`
        : `${minutes}:${shown}`;
};

export function startStopwatches(root: ParentNode): () => void {
    return eachWidget<HTMLElement>(
        root,
        '.widget-stopwatch',
        (widget, signal) => {
            const main = widget.querySelector<HTMLButtonElement>(
                '.widget-stopwatch-main',
            )!;
            const lap = widget.querySelector<HTMLButtonElement>(
                '.widget-stopwatch-lap',
            )!;
            const reset = widget.querySelector<HTMLButtonElement>(
                '.widget-stopwatch-reset',
            )!;
            const time = widget.querySelector<HTMLElement>(
                '.widget-stopwatch-time',
            )!;
            const laps = widget.querySelector<HTMLElement>(
                '.widget-stopwatch-laps',
            )!;
            const name =
                widget.querySelector('.widget-stopwatch-label')?.textContent ??
                'stopwatch';

            // Time counted before the last start, and when that start was.
            let banked = 0;
            let startedAt: number | null = null;
            let lastLap = 0;
            let frame = 0;

            const elapsed = () =>
                banked +
                (startedAt === null ? 0 : performance.now() - startedAt);

            const draw = () => {
                time.textContent = format(elapsed());

                if (startedAt !== null) {
                    frame = requestAnimationFrame(draw);
                }
            };

            const show = (state: 'idle' | 'running' | 'paused') => {
                widget.dataset.state = state;
                lap.hidden = state !== 'running';
                reset.hidden = state === 'idle';
                main.setAttribute(
                    'aria-label',
                    `${state === 'running' ? 'Pause' : state === 'paused' ? 'Resume' : 'Start'} ${name}`,
                );
            };

            main.addEventListener(
                'click',
                () => {
                    if (startedAt === null) {
                        startedAt = performance.now();
                        show('running');
                        draw();
                    } else {
                        banked = elapsed();
                        startedAt = null;
                        cancelAnimationFrame(frame);
                        draw();
                        show('paused');
                    }
                },
                { signal },
            );

            lap.addEventListener(
                'click',
                () => {
                    const now = elapsed();
                    const row = document.createElement('span');

                    row.setAttribute('role', 'listitem');
                    row.className = 'widget-stopwatch-lap-row';
                    row.textContent = `Lap ${laps.children.length + 1}: ${format(now - lastLap)} (${format(now)})`;
                    laps.prepend(row);
                    laps.hidden = false;
                    lastLap = now;
                },
                { signal },
            );

            reset.addEventListener(
                'click',
                () => {
                    banked = 0;
                    startedAt = null;
                    lastLap = 0;
                    cancelAnimationFrame(frame);
                    laps.replaceChildren();
                    laps.hidden = true;
                    draw();
                    show('idle');
                },
                { signal },
            );

            return () => cancelAnimationFrame(frame);
        },
    );
}
