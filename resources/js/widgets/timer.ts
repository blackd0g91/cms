import { eachWidget } from './each';

/*
 * {{ timer }} widgets (app/Cms/Widgets/TimerWidget.php). Tap to start, tap
 * again to pause, ↺ to reset. When one is done it rings until stopped.
 *
 * Browsers slow down scripts in background tabs, sometimes to once a minute,
 * so the alarm is not started by a script running on time: it is scheduled
 * ahead on the audio clock, which keeps time anyway.
 */

type State = 'idle' | 'running' | 'paused' | 'ringing';

// Shared by every timer on the page, wherever it was started.
let audio: AudioContext | null = null;
let screenLock: WakeLockSentinel | null = null;
const active = new Set<HTMLElement>();
// The page's title while an alarm shows in it.
let titleBeforeAlarm: string | null = null;
let watchingVisibility = false;

export const format = (seconds: number) => {
    const s = Math.max(0, Math.ceil(seconds));
    const hours = Math.floor(s / 3600);
    const minutes = Math.floor((s % 3600) / 60);
    const rest = String(s % 60).padStart(2, '0');

    return hours
        ? `${hours}:${String(minutes).padStart(2, '0')}:${rest}`
        : `${minutes}:${rest}`;
};

// While any timer runs or rings, keep the screen on: a sleeping phone stops
// everything, the alarm included.
const updateScreenLock = async () => {
    if (active.size && !screenLock && 'wakeLock' in navigator) {
        try {
            screenLock = await navigator.wakeLock.request('screen');
            screenLock.addEventListener('release', () => (screenLock = null));
        } catch {
            // Not allowed right now (like on low battery).
        }
    } else if (!active.size && screenLock) {
        await screenLock.release();
        screenLock = null;
    }
};

/** Three short beeps every 1.5 seconds, from `at` for `seconds`. */
const beeps = (at: number, seconds: number): OscillatorNode[] => {
    audio ??= new AudioContext();
    const ctx = audio;
    const nodes: OscillatorNode[] = [];

    for (let group = 0; group < seconds; group += 1.5) {
        for (let beep = 0; beep < 3; beep++) {
            const start = at + group + beep * 0.18;
            const oscillator = ctx.createOscillator();
            const gain = ctx.createGain();

            oscillator.frequency.value = 880;
            gain.gain.setValueAtTime(0, start);
            gain.gain.linearRampToValueAtTime(0.25, start + 0.01);
            gain.gain.linearRampToValueAtTime(0, start + 0.12);
            oscillator.connect(gain).connect(ctx.destination);
            oscillator.start(start);
            oscillator.stop(start + 0.13);
            nodes.push(oscillator);
        }
    }

    return nodes;
};

const silence = (nodes: OscillatorNode[]) => {
    nodes.forEach((node) => {
        try {
            node.stop();
            node.disconnect();
        } catch {
            // Already finished.
        }
    });
    nodes.length = 0;
};

export function startTimers(root: ParentNode): () => void {
    return eachWidget<HTMLElement>(
        root,
        '.widget-timer[data-timer]',
        (widget, signal) => {
            if (!watchingVisibility) {
                watchingVisibility = true;
                document.addEventListener('visibilitychange', () => {
                    if (document.visibilityState === 'visible') {
                        void updateScreenLock();
                    }
                });
            }

            const total = Number(widget.dataset.timer);
            const main =
                widget.querySelector<HTMLButtonElement>('.widget-timer-main')!;
            const reset = widget.querySelector<HTMLButtonElement>(
                '.widget-timer-reset',
            )!;
            const time =
                widget.querySelector<HTMLElement>('.widget-timer-time')!;
            const name =
                widget.querySelector('.widget-timer-label')?.textContent ??
                `${format(total)} timer`;

            let state: State = 'idle';
            let remaining = total;
            let endsAt = 0;
            let ticker: number | undefined;
            let ringer: number | undefined;
            const scheduled: OscillatorNode[] = [];
            // How far ahead (on the audio clock) the alarm is scheduled.
            let alarmUntil = 0;

            // Beeps from where the scheduled ones end, up to a minute from now.
            const keepRinging = () => {
                if (!audio) {
                    return;
                }

                const from = Math.max(audio.currentTime + 0.05, alarmUntil);
                const until = audio.currentTime + 60;

                if (until > from) {
                    scheduled.push(...beeps(from, until - from));
                    alarmUntil = until;
                }
            };

            const show = (next: State) => {
                state = next;
                widget.dataset.state = next;
                reset.hidden = next === 'idle' || next === 'ringing';
                main.setAttribute(
                    'aria-label',
                    {
                        idle: `Start ${name}`,
                        running: `Pause ${name}`,
                        paused: `Resume ${name}`,
                        ringing: `Stop the alarm of ${name}`,
                    }[next],
                );

                if (next === 'idle' || next === 'paused') {
                    active.delete(widget);
                } else {
                    active.add(widget);
                }

                void updateScreenLock();
            };

            const tick = () => {
                const left = (endsAt - Date.now()) / 1000;

                if (left > 0) {
                    time.textContent = format(left);

                    return;
                }

                // Done. The first minute of beeps is already scheduled; keep
                // adding more until it is stopped.
                window.clearInterval(ticker);
                time.textContent = "Time's up!";
                show('ringing');
                titleBeforeAlarm ??= document.title;
                document.title = `⏰ ${name} - ${titleBeforeAlarm}`;
                navigator.vibrate?.([400, 200, 400, 200, 400]);

                ringer = window.setInterval(() => {
                    keepRinging();
                    navigator.vibrate?.([400, 200, 400]);
                }, 20_000);
            };

            const start = () => {
                audio ??= new AudioContext();
                void audio.resume();

                endsAt = Date.now() + remaining * 1000;
                scheduled.push(...beeps(audio.currentTime + remaining, 60));
                alarmUntil = audio.currentTime + remaining + 60;
                ticker = window.setInterval(tick, 250);
                tick();
                show('running');
            };

            const stop = (next: 'idle' | 'paused') => {
                window.clearInterval(ticker);
                window.clearInterval(ringer);
                silence(scheduled);
                navigator.vibrate?.(0);

                if (next === 'paused') {
                    remaining = Math.max(1, (endsAt - Date.now()) / 1000);
                } else {
                    remaining = total;
                    time.textContent = format(total);
                }

                const othersRinging = [...active].some(
                    (other) =>
                        other !== widget && other.dataset.state === 'ringing',
                );

                if (!othersRinging && titleBeforeAlarm !== null) {
                    document.title = titleBeforeAlarm;
                    titleBeforeAlarm = null;
                }

                show(next);
            };

            main.addEventListener(
                'click',
                () => {
                    switch (state) {
                        case 'idle':
                        case 'paused':
                            start();
                            break;
                        case 'running':
                            stop('paused');
                            break;
                        case 'ringing':
                            stop('idle');
                            break;
                    }
                },
                { signal },
            );

            reset.addEventListener('click', () => stop('idle'), { signal });

            // Taken off the page (a preview redrawn): no more ticking or beeping.
            return () => {
                if (state !== 'idle') {
                    stop('idle');
                }
            };
        },
    );
}
