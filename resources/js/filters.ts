/**
 * Buttons that narrow down a list, like the free games by device. The
 * buttons sit in [data-filters="{list id}"], each with data-filter="{tag}"
 * (empty for all), and the list's items say their tags in
 * data-filter-tags="{tag}|{tag}". Without the script the whole list shows.
 */
export function startFilters(root: ParentNode = document) {
    root.querySelectorAll<HTMLElement>('[data-filters]').forEach((group) => {
        const list = document.getElementById(group.dataset.filters ?? '');
        const buttons = [
            ...group.querySelectorAll<HTMLButtonElement>('[data-filter]'),
        ];

        if (!list) {
            return;
        }

        buttons.forEach((button) =>
            button.addEventListener('click', () => {
                const tag = button.dataset.filter ?? '';

                buttons.forEach((other) =>
                    other.setAttribute(
                        'aria-pressed',
                        String(other === button),
                    ),
                );

                list.querySelectorAll<HTMLElement>(
                    '[data-filter-tags]',
                ).forEach((item) => {
                    item.hidden =
                        tag !== '' &&
                        !(item.dataset.filterTags ?? '')
                            .split('|')
                            .includes(tag);
                });
            }),
        );
    });
}
