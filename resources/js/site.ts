import { applyTheme, currentTheme, nextTheme, themeTitle } from './lib/theme';
import type { Theme } from './lib/theme';
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

// Headings link to themselves (see app/Cms/TableOfContents.php). Following
// one puts its section in the address as usual, and also copies the link.
document
    .querySelectorAll<HTMLAnchorElement>('.prose a.heading-link')
    .forEach((link) => {
        let timeout: number | undefined;

        link.addEventListener('click', async () => {
            try {
                await navigator.clipboard.writeText(link.href);
            } catch {
                // Not allowed (like over plain http): the address bar has it.
                return;
            }

            link.dataset.copied = '';
            window.clearTimeout(timeout);
            timeout = window.setTimeout(() => delete link.dataset.copied, 1500);
        });
    });

// Images in posts open larger when clicked, with their caption. Linked
// images follow their link, and small ones within a line of text stay put.
const zoomable = [
    ...document.querySelectorAll<HTMLImageElement>('.prose img'),
].filter((image) => {
    const parent = image.parentElement;
    const inText =
        parent?.tagName === 'P' && (parent.textContent ?? '').trim() !== '';

    return !image.closest('a, button') && !inText;
});

if (zoomable.length) {
    const viewer = document.createElement('dialog');
    const large = document.createElement('img');
    const caption = document.createElement('p');
    const close = document.createElement('button');

    viewer.className = 'lightbox';
    caption.className = 'lightbox-caption';
    close.type = 'button';
    close.className = 'lightbox-close';
    close.textContent = 'Close';
    viewer.append(close, large, caption);
    document.body.append(viewer);

    // The image it was opened from, to go back to.
    let opener: HTMLButtonElement | null = null;

    // A click anywhere closes it, as does Esc.
    viewer.addEventListener('click', () => viewer.close());
    viewer.addEventListener('close', () => {
        large.removeAttribute('srcset');
        large.removeAttribute('src');
        // Browsers only do it themselves when the button had focus, which
        // a click does not give it in Safari.
        opener?.focus({ preventScroll: true });
    });

    zoomable.forEach((image) => {
        const button = document.createElement('button');

        button.type = 'button';
        button.className = 'image-zoom';
        button.setAttribute(
            'aria-label',
            image.alt ? `Enlarge: ${image.alt}` : 'Enlarge image',
        );
        image.replaceWith(button);
        button.append(image);

        button.addEventListener('click', () => {
            const text =
                image.closest('figure')?.querySelector('figcaption')
                    ?.textContent ?? '';

            // The original, or the resized copy that fits the screen.
            large.srcset = image.srcset;
            large.sizes = '100vw';
            large.src = image.getAttribute('src') ?? image.currentSrc;
            large.alt = image.alt;
            caption.textContent = text;
            caption.hidden = text === '';
            opener = button;
            viewer.showModal();
        });
    });
}

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

// Light / dark / system theme (see lib/theme.ts); this only handles the button.
const themeButton = document.querySelector<HTMLButtonElement>(
    '[data-theme-toggle]',
);

if (themeButton) {
    const apply = (theme: Theme) => {
        applyTheme(theme);

        themeButton
            .querySelectorAll<SVGElement>('[data-theme-icon]')
            .forEach((icon) => {
                icon.toggleAttribute(
                    'hidden',
                    icon.dataset.themeIcon !== theme,
                );
            });

        themeButton.title = themeTitle(theme);
        themeButton.setAttribute('aria-label', themeButton.title);
    };

    themeButton.hidden = false;
    apply(currentTheme());
    themeButton.addEventListener('click', () => {
        apply(nextTheme(currentTheme()));
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
