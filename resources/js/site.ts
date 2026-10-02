import { startWidgets } from './widgets';

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

// {{ widgets }} in posts: timers, stopwatches, temperatures and the rest.
startWidgets(document);
