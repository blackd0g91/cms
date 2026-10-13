/**
 * Toasts: short notices in the top right corner of the site, each closed
 * with its ×. Most close by themselves after a few seconds, waiting while
 * the pointer or the keyboard is on them; those that ask something, like a
 * reminder with its Done and Snooze, stay until closed. The layout has the
 * corner, with what the site just did if anything (like "Done." after
 * marking a reminder done, see site/layout.blade.php); scripts add their
 * own, like reminders coming due (see reminders.ts).
 */

type Toast = {
    // A small heading above the text, like "Reminder".
    label?: string;
    text: string;
    // Buttons or forms in a row below the text.
    actions?: HTMLElement[];
    // When closed with its ×.
    onClose?: () => void;
    // Stays until closed, instead of closing by itself.
    stay?: boolean;
};

// How long a toast shows before closing by itself, and how long it takes to
// fade out (see .toast-leaving in site.css).
export const TOAST_SECONDS = 6;
const FADE = 200;

/** The corner the toasts show in, made when the layout has none. */
export const toastRegion = () => {
    const existing = document.querySelector<HTMLElement>('[data-toasts]');

    if (existing) {
        return existing;
    }

    const region = document.createElement('div');
    region.className = 'toasts';
    region.dataset.toasts = '';
    region.setAttribute('aria-live', 'polite');
    document.body.append(region);

    return region;
};

const closeWith = (box: HTMLElement, onClose?: () => void) => {
    box.querySelector('[data-toast-close]')?.addEventListener('click', () => {
        box.remove();
        onClose?.();
    });
};

/** Closes the toast after a while, waiting while it is hovered or focused. */
const closeLater = (box: HTMLElement) => {
    let timer = 0;
    let hovered = false;

    const wait = () => {
        window.clearTimeout(timer);

        if (hovered || box.contains(document.activeElement)) {
            return;
        }

        timer = window.setTimeout(() => {
            box.classList.add('toast-leaving');
            window.setTimeout(() => box.remove(), FADE);
        }, TOAST_SECONDS * 1000);
    };

    box.addEventListener('pointerenter', () => {
        hovered = true;
        wait();
    });
    box.addEventListener('pointerleave', () => {
        hovered = false;
        wait();
    });
    box.addEventListener('focusin', wait);
    // Focus has moved by then.
    box.addEventListener('focusout', () => window.setTimeout(wait));

    wait();
};

/** Shows a toast in the corner, newest at the bottom. */
export function toast({ label, text, actions = [], onClose, stay }: Toast) {
    const box = document.createElement('div');
    box.className = 'toast';
    box.setAttribute('role', 'status');

    const close = document.createElement('button');
    close.type = 'button';
    close.className = 'toast-close';
    close.dataset.toastClose = '';
    close.setAttribute('aria-label', 'Close');
    close.textContent = '×';
    box.append(close);

    if (label) {
        const heading = document.createElement('p');
        heading.className = 'toast-label';
        heading.textContent = label;
        box.append(heading);
    }

    const body = document.createElement('p');
    body.className = 'toast-text';
    body.textContent = text;
    box.append(body);

    if (actions.length > 0) {
        const row = document.createElement('div');
        row.className = 'toast-actions';
        row.append(...actions);
        box.append(row);
    }

    closeWith(box, onClose);
    toastRegion().append(box);

    if (!stay) {
        closeLater(box);
    }

    return box;
}

/** The toasts the layout came with: their ×, and closing by themselves. */
export function startToasts(root: ParentNode = document) {
    root.querySelectorAll<HTMLElement>('[data-toasts] .toast').forEach(
        (box) => {
            closeWith(box);
            closeLater(box);
        },
    );
}
