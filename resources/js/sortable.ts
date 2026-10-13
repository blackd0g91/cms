/**
 * Lists whose items can be moved up and down with buttons, like the home
 * page cards on the account page (see site/account/edit.blade.php). The
 * items are moved in the page, so a form around them sends their fields in
 * the new order. The buttons are hidden until this shows them, as they do
 * nothing without it.
 */
export function startSortables(root: ParentNode = document) {
    root.querySelectorAll<HTMLElement>('[data-sortable]').forEach((list) => {
        const items = () => [
            ...list.querySelectorAll<HTMLElement>(
                ':scope > [data-sortable-item]',
            ),
        ];

        // The first can not go up, nor the last down.
        const update = () => {
            const all = items();

            all.forEach((item, i) => {
                item.querySelector<HTMLButtonElement>(
                    '[data-move="up"]',
                )!.disabled = i === 0;
                item.querySelector<HTMLButtonElement>(
                    '[data-move="down"]',
                )!.disabled = i === all.length - 1;
            });
        };

        list.addEventListener('click', (event) => {
            const button = (event.target as Element).closest<HTMLButtonElement>(
                '[data-move]',
            );
            const item = button?.closest<HTMLElement>('[data-sortable-item]');

            if (!button || !item) {
                return;
            }

            if (button.dataset.move === 'up') {
                item.previousElementSibling?.before(item);
            } else {
                item.nextElementSibling?.after(item);
            }

            update();

            // Keyboard users carry on from the same button, or from the
            // other one once this one can go no further.
            const other = button.dataset.move === 'up' ? 'down' : 'up';
            const next = button.disabled
                ? item.querySelector<HTMLButtonElement>(
                      `[data-move="${other}"]`,
                  )
                : button;
            next?.focus();
        });

        list.querySelectorAll<HTMLElement>('[data-sortable-buttons]').forEach(
            (buttons) => {
                buttons.hidden = false;
            },
        );
        update();
    });
}
