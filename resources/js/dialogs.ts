/**
 * Dialogs opened from a link or button with data-dialog-open="{dialog id}",
 * like the picture of the day on the home page. The opener stays a working
 * link without the script. Inside, [data-dialog-close] closes the dialog, as
 * do Esc and a click on the backdrop. A dialog with data-dialog-open-now
 * opens as the page does.
 */
export function startDialogs(root: ParentNode = document) {
    root.querySelectorAll<HTMLElement>('[data-dialog-open]').forEach(
        (opener) => {
            const dialog = document.getElementById(
                opener.dataset.dialogOpen ?? '',
            );

            if (!(dialog instanceof HTMLDialogElement)) {
                return;
            }

            opener.addEventListener('click', (event) => {
                // Opening in a new tab still follows the link.
                if (event.metaKey || event.ctrlKey || event.shiftKey) {
                    return;
                }

                event.preventDefault();
                dialog.showModal();
            });

            dialog
                .querySelectorAll('[data-dialog-close]')
                .forEach((button) =>
                    button.addEventListener('click', () => dialog.close()),
                );

            // The backdrop is the dialog itself, outside its box.
            dialog.addEventListener('click', (event) => {
                const box = dialog.getBoundingClientRect();
                const outside =
                    event.clientX < box.left ||
                    event.clientX > box.right ||
                    event.clientY < box.top ||
                    event.clientY > box.bottom;

                if (event.target === dialog && outside) {
                    dialog.close();
                }
            });

            // Open from the start, like a form sent back with an error.
            if (dialog.hasAttribute('data-dialog-open-now')) {
                dialog.showModal();
            }
        },
    );
}
