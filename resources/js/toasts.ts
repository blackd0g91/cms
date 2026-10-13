/**
 * Toasts: short notices in the top right corner of the site, each closed
 * with its ×. The layout has the corner, with what the site just did if
 * anything (like "Done." after marking a reminder done, see
 * site/layout.blade.php); scripts add their own, like reminders coming due
 * (see reminders.ts).
 */

type Toast = {
    // A small heading above the text, like "Reminder".
    label?: string;
    text: string;
    // Buttons or forms in a row below the text.
    actions?: HTMLElement[];
    // When closed with its ×.
    onClose?: () => void;
};

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

/** Shows a toast in the corner, newest at the bottom. */
export function toast({ label, text, actions = [], onClose }: Toast) {
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

    return box;
}

/** The × on the toasts the layout came with. */
export function startToasts(root: ParentNode = document) {
    root.querySelectorAll<HTMLElement>('[data-toasts] .toast').forEach((box) =>
        closeWith(box),
    );
}
