import { notifyOn } from './reminders';
import { toast } from './toasts';

/**
 * The focus timer card (see site/cards/focus-timer.blade.php): a pomodoro
 * timer kept in this browser, so it goes on across reloads and other tabs.
 * When time is up it says so in a toast, with a button to start what comes
 * next, and in a system notification where the member turned those on.
 */

type Mode = 'focus' | 'short' | 'long';

type State = {
    mode: Mode;
    running: boolean;
    // When it ends, while running.
    endsAt: number;
    // What is left, while paused.
    remaining: number;
    // Focus sessions done today (a local date like 2026-10-14).
    day: string;
    sessions: number;
};

const KEY = 'site.focus-timer';

export const MINUTES: Record<Mode, number> = { focus: 25, short: 5, long: 15 };

const LABELS: Record<Mode, string> = {
    focus: 'Focus',
    short: 'Short break',
    long: 'Long break',
};

const duration = (mode: Mode) => MINUTES[mode] * 60_000;

const today = () => {
    const now = new Date();

    return `${now.getFullYear()}-${now.getMonth() + 1}-${now.getDate()}`;
};

const fresh = (): State => ({
    mode: 'focus',
    running: false,
    endsAt: 0,
    remaining: duration('focus'),
    day: today(),
    sessions: 0,
});

const load = (): State => {
    try {
        const saved = JSON.parse(
            localStorage.getItem(KEY) ?? 'null',
        ) as State | null;

        if (saved && saved.mode in MINUTES) {
            return saved.day === today()
                ? saved
                : { ...saved, day: today(), sessions: 0 };
        }
    } catch {
        // Not kept, or not readable: start afresh.
    }

    return fresh();
};

const save = (state: State) => {
    try {
        localStorage.setItem(KEY, JSON.stringify(state));
    } catch {
        // Not kept (private windows): the timer still runs in this tab.
    }
};

export const left = (state: State, now = Date.now()) =>
    state.running ? Math.max(0, state.endsAt - now) : state.remaining;

export const clock = (ms: number) => {
    const seconds = Math.ceil(ms / 1000);

    return `${String(Math.floor(seconds / 60)).padStart(2, '0')}:${String(seconds % 60).padStart(2, '0')}`;
};

/** A short chime, where the browser lets the page make sounds. */
const chime = () => {
    try {
        const audio = new AudioContext();
        const tone = audio.createOscillator();
        const volume = audio.createGain();

        tone.frequency.value = 880;
        volume.gain.setValueAtTime(0.15, audio.currentTime);
        volume.gain.exponentialRampToValueAtTime(0.001, audio.currentTime + 1);
        tone.connect(volume).connect(audio.destination);
        tone.start();
        tone.stop(audio.currentTime + 1);
    } catch {
        // No sound, then: the toast still shows.
    }
};

export function startFocusTimer(root: ParentNode = document) {
    const cards = [
        ...root.querySelectorAll<HTMLElement>(
            '[data-focus-timer], [data-focus-timer-panel]',
        ),
    ];

    if (cards.length === 0) {
        return;
    }

    let state = load();
    let timer = 0;

    const all = <T extends Element>(selector: string) =>
        cards.flatMap((card) => [...card.querySelectorAll<T>(selector)]);

    const render = () => {
        const status = state.running
            ? 'Running'
            : state.remaining < duration(state.mode)
              ? 'Paused'
              : 'Ready';

        all('[data-timer-time]').forEach((el) => {
            el.textContent = clock(left(state));
        });
        all('[data-timer-mode]').forEach((el) => {
            el.textContent = LABELS[state.mode];
        });
        all('[data-timer-state]').forEach((el) => {
            el.textContent = status;
        });
        all('[data-timer-sessions]').forEach((el) => {
            el.textContent = String(state.sessions);
        });
        all('[data-timer-toggle-label]').forEach((el) => {
            el.textContent = state.running ? 'Pause' : 'Start';
        });
        all<SVGElement>('[data-timer-icon]').forEach((icon) => {
            icon.toggleAttribute(
                'hidden',
                icon.dataset.timerIcon !== (state.running ? 'pause' : 'play'),
            );
        });
        all<HTMLElement>('[data-timer-choose]').forEach((button) => {
            button.setAttribute(
                'aria-pressed',
                String(button.dataset.timerChoose === state.mode),
            );
        });
    };

    const set = (next: State) => {
        state = next;
        save(state);
        render();
        run();
    };

    const begin = (mode: Mode) =>
        set({
            ...state,
            mode,
            running: true,
            endsAt: Date.now() + duration(mode),
            remaining: duration(mode),
        });

    const finish = () => {
        // Another tab may have seen it end first.
        const saved = load();

        if (!saved.running || saved.endsAt > Date.now()) {
            set(saved);

            return;
        }

        const focused = saved.mode === 'focus';
        const sessions = saved.sessions + (focused ? 1 : 0);
        const next: Mode = focused
            ? sessions % 4 === 0
                ? 'long'
                : 'short'
            : 'focus';
        const text = focused
            ? `Focus done. Time for a ${next === 'long' ? 'long' : 'short'} break.`
            : 'Break over. Back to focus.';

        set({
            ...saved,
            mode: next,
            running: false,
            remaining: duration(next),
            sessions,
        });

        const start = document.createElement('button');
        start.type = 'button';
        start.className = 'toast-primary';
        start.textContent = `Start ${LABELS[next].toLowerCase()}`;
        start.addEventListener('click', () => {
            begin(next);
            start.closest('.toast')?.remove();
        });

        toast({ label: 'Focus timer', text, actions: [start], stay: true });
        chime();

        if (document.visibilityState !== 'visible' && notifyOn()) {
            new Notification('Focus timer', { body: text, tag: 'focus-timer' });
        }
    };

    // Ticks only while running.
    function run() {
        window.clearInterval(timer);

        if (state.running) {
            timer = window.setInterval(() => {
                if (left(state) === 0) {
                    window.clearInterval(timer);
                    finish();
                } else {
                    render();
                }
            }, 250);
        }
    }

    all('[data-timer-toggle]').forEach((button) =>
        button.addEventListener('click', () => {
            const now = Date.now();

            set(
                state.running
                    ? { ...state, running: false, remaining: left(state, now) }
                    : {
                          ...state,
                          running: true,
                          endsAt: now + state.remaining,
                      },
            );
        }),
    );
    all('[data-timer-reset]').forEach((button) =>
        button.addEventListener('click', () =>
            set({
                ...state,
                running: false,
                remaining: duration(state.mode),
            }),
        ),
    );
    all<HTMLElement>('[data-timer-choose]').forEach((button) =>
        button.addEventListener('click', () => {
            const mode = button.dataset.timerChoose as Mode;

            set({ ...state, mode, running: false, remaining: duration(mode) });
        }),
    );

    // Started, paused or finished in another tab.
    window.addEventListener('storage', (event) => {
        if (event.key === KEY) {
            state = load();
            render();
            run();
        }
    });

    cards.forEach((card) => {
        card.hidden = false;
    });

    // Ended while the page was closed.
    if (state.running && left(state) === 0) {
        finish();
    } else {
        render();
        run();
    }
}
