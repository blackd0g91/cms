/**
 * Small enhancements for the public site. Everything works without them.
 */

// Press "/" anywhere to jump to the search box.
document.addEventListener('keydown', (event) => {
    const target = event.target as HTMLElement;
    const typing =
        target.isContentEditable ||
        ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName);

    if (event.key === '/' && !typing && !event.ctrlKey && !event.metaKey) {
        event.preventDefault();
        document.querySelector<HTMLInputElement>('#site-search')?.focus();
    }
});

// A copy button on every code block, for cheatsheets.
document.querySelectorAll<HTMLPreElement>('.prose pre').forEach((pre) => {
    const button = document.createElement('button');
    button.type = 'button';
    button.textContent = 'Copy';
    button.className =
        'absolute top-2 right-2 rounded-md border border-line bg-card px-2 py-0.5 font-mono text-xs text-muted opacity-0 transition group-hover:opacity-100 focus:opacity-100 hover:text-ink';

    button.addEventListener('click', async () => {
        const code = pre.querySelector('code')?.innerText ?? pre.innerText;

        try {
            await navigator.clipboard.writeText(code);
            button.textContent = 'Copied';
        } catch {
            button.textContent = 'Press Ctrl+C';
        }

        setTimeout(() => (button.textContent = 'Copy'), 1500);
    });

    pre.classList.add('group');
    pre.append(button);
});

// Highlight the table of contents entry for the section being read.
const tocLinks = Array.from(
    document.querySelectorAll<HTMLAnchorElement>('[data-toc] a[href^="#"]'),
);

const tocTargets = [...new Set(tocLinks.map((link) => link.hash.slice(1)))]
    .map((id) => document.getElementById(id))
    .filter((heading): heading is HTMLElement => heading !== null);

if (tocTargets.length > 0) {
    const setActive = (id: string) =>
        tocLinks.forEach((link) =>
            link.toggleAttribute('data-active', link.hash === `#${id}`),
        );

    const update = () => {
        // The last heading above the top quarter of the screen is "current".
        const line = window.innerHeight * 0.25;
        const current =
            tocTargets
                .filter((h) => h.getBoundingClientRect().top <= line)
                .pop() ?? tocTargets[0];

        setActive(current.id);
    };

    window.addEventListener('scroll', update, { passive: true });
    update();
}

// "Keep screen on", for following a recipe without the phone going to sleep.
// Browsers release the lock when the tab is hidden, so it is taken again
// when the page comes back.
const wakeButton =
    document.querySelector<HTMLButtonElement>('[data-wake-lock]');

if (wakeButton && 'wakeLock' in navigator) {
    const label = wakeButton.querySelector('[data-wake-lock-label]');
    let lock: WakeLockSentinel | null = null;
    let wanted = false;

    const render = () => {
        const on = lock !== null && !lock.released;
        wakeButton.setAttribute('aria-pressed', String(on));

        if (label) {
            label.textContent = on ? 'Screen stays on' : 'Keep screen on';
        }
    };

    const acquire = async () => {
        try {
            lock = await navigator.wakeLock.request('screen');
            lock.addEventListener('release', render);
        } catch {
            // Refused (for example on low battery); leave the button off.
            wanted = false;
            lock = null;
        }

        render();
    };

    wakeButton.hidden = false;

    wakeButton.addEventListener('click', async () => {
        wanted = !(lock !== null && !lock.released);

        if (wanted) {
            await acquire();
        } else {
            await lock?.release();
            lock = null;
            render();
        }
    });

    document.addEventListener('visibilitychange', () => {
        if (wanted && document.visibilityState === 'visible') {
            void acquire();
        }
    });
}

// Light / dark / system theme. The choice is applied in <head> before the
// page draws (see site/layout.blade.php); this only handles the button.
const themeButton = document.querySelector<HTMLButtonElement>(
    '[data-theme-toggle]',
);

if (themeButton) {
    type Theme = 'system' | 'light' | 'dark';

    const order: Theme[] = ['system', 'light', 'dark'];
    const labels: Record<Theme, string> = {
        system: 'Theme: follows your system',
        light: 'Theme: light',
        dark: 'Theme: dark',
    };

    const current = (): Theme => {
        const theme = document.documentElement.dataset.theme;

        return theme === 'light' || theme === 'dark' ? theme : 'system';
    };

    const apply = (theme: Theme) => {
        // Switch every color at once, instead of some elements fading over.
        document.documentElement.classList.add('theme-switching');
        requestAnimationFrame(() =>
            requestAnimationFrame(() =>
                document.documentElement.classList.remove('theme-switching'),
            ),
        );

        if (theme === 'system') {
            delete document.documentElement.dataset.theme;
        } else {
            document.documentElement.dataset.theme = theme;
        }

        try {
            localStorage.setItem('site.theme', theme);
        } catch {
            // Not remembering the choice is fine.
        }

        themeButton
            .querySelectorAll<SVGElement>('[data-theme-icon]')
            .forEach((icon) => {
                icon.toggleAttribute(
                    'hidden',
                    icon.dataset.themeIcon !== theme,
                );
            });

        const next = order[(order.indexOf(theme) + 1) % order.length];
        themeButton.title = `${labels[theme]}. Click for ${next}.`;
        themeButton.setAttribute('aria-label', themeButton.title);
    };

    themeButton.hidden = false;
    apply(current());
    themeButton.addEventListener('click', () => {
        apply(order[(order.indexOf(current()) + 1) % order.length]);
    });
}

// Popovers made with <details data-popover> (like "Open on phone") close on
// a click elsewhere or Escape, like menus do.
const popovers = document.querySelectorAll<HTMLDetailsElement>(
    'details[data-popover]',
);

if (popovers.length) {
    document.addEventListener('click', (event) => {
        popovers.forEach((popover) => {
            if (popover.open && !popover.contains(event.target as Node)) {
                popover.open = false;
            }
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            popovers.forEach((popover) => (popover.open = false));
        }
    });
}

// {{ timer }} widgets (app/Cms/Widgets/TimerWidget.php). Tap to start, tap
// again to pause, ↺ to reset. When one is done it rings until stopped.
//
// Browsers slow down scripts in background tabs, sometimes to once a minute,
// so the alarm is not started by a script running on time: it is scheduled
// ahead on the audio clock, which keeps time anyway.
const timerWidgets = document.querySelectorAll<HTMLElement>(
    '.widget-timer[data-timer]',
);

if (timerWidgets.length) {
    type State = 'idle' | 'running' | 'paused' | 'ringing';

    let audio: AudioContext | null = null;
    let screenLock: WakeLockSentinel | null = null;
    const active = new Set<HTMLElement>();
    const pageTitle = document.title;

    const format = (seconds: number) => {
        const s = Math.max(0, Math.ceil(seconds));
        const hours = Math.floor(s / 3600);
        const minutes = Math.floor((s % 3600) / 60);
        const rest = String(s % 60).padStart(2, '0');

        return hours
            ? `${hours}:${String(minutes).padStart(2, '0')}:${rest}`
            : `${minutes}:${rest}`;
    };

    // While any timer runs or rings, keep the screen on: a sleeping phone
    // stops everything, the alarm included.
    const updateScreenLock = async () => {
        if (active.size && !screenLock && 'wakeLock' in navigator) {
            try {
                screenLock = await navigator.wakeLock.request('screen');
                screenLock.addEventListener(
                    'release',
                    () => (screenLock = null),
                );
            } catch {
                // Not allowed right now (like on low battery).
            }
        } else if (!active.size && screenLock) {
            await screenLock.release();
            screenLock = null;
        }
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            void updateScreenLock();
        }
    });

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

    timerWidgets.forEach((widget) => {
        const total = Number(widget.dataset.timer);
        const main =
            widget.querySelector<HTMLButtonElement>('.widget-timer-main')!;
        const reset = widget.querySelector<HTMLButtonElement>(
            '.widget-timer-reset',
        )!;
        const time = widget.querySelector<HTMLElement>('.widget-timer-time')!;
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
            document.title = `⏰ ${name} - ${pageTitle}`;
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

            if (
                ![...active].some(
                    (other) =>
                        other !== widget && other.dataset.state === 'ringing',
                )
            ) {
                document.title = pageTitle;
            }

            show(next);
        };

        main.addEventListener('click', () => {
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
        });

        reset.addEventListener('click', () => stop('idle'));
    });
}

// {{ temp }} widgets show °C and °F. Clicking one swaps which comes first,
// everywhere on the site, and that choice is remembered.
const temperatures =
    document.querySelectorAll<HTMLButtonElement>('.widget-temp');

if (temperatures.length) {
    try {
        const saved = localStorage.getItem('site.temperature');

        if (saved === 'c' || saved === 'f') {
            document.documentElement.dataset.temperature = saved;
        }
    } catch {
        // Not remembered (private windows): the written unit comes first.
    }

    temperatures.forEach((button) =>
        button.addEventListener('click', () => {
            const current =
                document.documentElement.dataset.temperature ??
                button.dataset.first;
            const next = current === 'f' ? 'c' : 'f';

            document.documentElement.dataset.temperature = next;

            try {
                localStorage.setItem('site.temperature', next);
            } catch {
                // Still swapped, just not remembered.
            }
        }),
    );
}

// {{ copy }} widgets: the button copies the text.
document
    .querySelectorAll<HTMLElement>('.widget-copy[data-copy]')
    .forEach((widget) => {
        const button = widget.querySelector<HTMLButtonElement>(
            '.widget-copy-button',
        );
        let timeout: number | undefined;

        button?.addEventListener('click', async () => {
            const text = widget.dataset.copy ?? '';

            try {
                await navigator.clipboard.writeText(text);
            } catch {
                // Without clipboard access (like over plain http), the old way.
                const field = Object.assign(
                    document.createElement('textarea'),
                    { value: text },
                );
                document.body.append(field);
                field.select();
                document.execCommand('copy');
                field.remove();
            }

            widget.dataset.copied = '';
            window.clearTimeout(timeout);
            timeout = window.setTimeout(
                () => delete widget.dataset.copied,
                1500,
            );
        });
    });

// {{ youtube }}: nothing is loaded from YouTube until the video is played.
document
    .querySelectorAll<HTMLElement>('.widget-youtube[data-youtube]')
    .forEach((widget) => {
        widget
            .querySelector('.widget-youtube-play')
            ?.addEventListener('click', (event) => {
                event.preventDefault();

                const start = Number(widget.dataset.start) || 0;
                const player = document.createElement('iframe');

                player.src = `https://www.youtube-nocookie.com/embed/${widget.dataset.youtube}?autoplay=1${start ? `&start=${start}` : ''}`;
                player.title =
                    widget.querySelector('.widget-youtube-title')
                        ?.textContent ?? 'YouTube video';
                player.allow =
                    'autoplay; encrypted-media; picture-in-picture; fullscreen';
                player.allowFullscreen = true;
                player.className = 'widget-youtube-player';

                widget.replaceChildren(player);
                widget.dataset.playing = '';
            });
    });

// {{ countdown }}: kept up to date, in the reader's own time zone. The
// wording matches CountdownWidget::describe().
const countdowns = document.querySelectorAll<HTMLElement>(
    '.widget-countdown[data-countdown]',
);

if (countdowns.length) {
    const describe = (target: Date, dateOnly: boolean) => {
        const now = new Date();

        if (dateOnly) {
            const day = (d: Date) =>
                Date.UTC(d.getFullYear(), d.getMonth(), d.getDate());
            const days = Math.round((day(target) - day(now)) / 86_400_000);

            if (days === 0) return 'Today!';
            if (days === 1) return 'Tomorrow';
            if (days === -1) return 'Yesterday';

            return days > 1 ? `${days} days to go` : `${-days} days ago`;
        }

        const signed = Math.floor((target.getTime() - now.getTime()) / 60_000);
        const minutes = Math.abs(signed);
        const days = Math.floor(minutes / 1440);
        const hours = Math.floor((minutes % 1440) / 60);
        const rest = minutes % 60;
        const span =
            days >= 2
                ? `${days} days`
                : days === 1
                  ? `1 day ${hours} h`
                  : hours > 0
                    ? `${hours} h ${rest} min`
                    : minutes > 0
                      ? `${minutes} min`
                      : null;

        return span === null
            ? 'Now!'
            : signed >= 0
              ? `${span} to go`
              : `${span} ago`;
    };

    const update = () =>
        countdowns.forEach((widget) => {
            const [date, time = '00:00'] = (
                widget.dataset.countdown ?? ''
            ).split('T');
            const [year, month, day] = date.split('-').map(Number);
            const [hour, minute] = time.split(':').map(Number);
            const value = widget.querySelector('.widget-countdown-value');

            if (value) {
                value.textContent = describe(
                    new Date(year, month - 1, day, hour, minute),
                    widget.hasAttribute('data-date-only'),
                );
            }
        });

    update();
    window.setInterval(update, 30_000);
}

// {{ spoiler }}: tap to show, tap again to hide.
document
    .querySelectorAll<HTMLButtonElement>('.widget-spoiler')
    .forEach((spoiler) =>
        spoiler.addEventListener('click', () => {
            const shown = spoiler.getAttribute('aria-expanded') !== 'true';

            spoiler.setAttribute('aria-expanded', String(shown));
            spoiler.title = shown ? 'Hide' : 'Show';
        }),
    );

// {{ stopwatch }}: start and pause, laps and reset.
document
    .querySelectorAll<HTMLElement>('.widget-stopwatch')
    .forEach((widget) => {
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
            banked + (startedAt === null ? 0 : performance.now() - startedAt);

        const format = (ms: number) => {
            const tenths = Math.floor(ms / 100);
            const hours = Math.floor(tenths / 36_000);
            const minutes = Math.floor((tenths % 36_000) / 600);
            const seconds = String(Math.floor((tenths % 600) / 10)).padStart(
                2,
                '0',
            );
            const shown = `${seconds}.${tenths % 10}`;

            return hours
                ? `${hours}:${String(minutes).padStart(2, '0')}:${shown}`
                : `${minutes}:${shown}`;
        };

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

        main.addEventListener('click', () => {
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
        });

        lap.addEventListener('click', () => {
            const now = elapsed();
            const row = document.createElement('span');

            row.setAttribute('role', 'listitem');
            row.className = 'widget-stopwatch-lap-row';
            row.textContent = `Lap ${laps.children.length + 1}: ${format(now - lastLap)} (${format(now)})`;
            laps.prepend(row);
            laps.hidden = false;
            lastLap = now;
        });

        reset.addEventListener('click', () => {
            banked = 0;
            startedAt = null;
            lastLap = 0;
            cancelAnimationFrame(frame);
            laps.replaceChildren();
            laps.hidden = true;
            draw();
            show('idle');
        });
    });
