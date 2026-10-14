/**
 * The quick colors under the new label form (see
 * site/labels/dialog.blade.php): picking one sets the color square, which
 * also picks any other.
 */
export function startLabelSwatches(root: ParentNode = document) {
    root.querySelectorAll<HTMLElement>('[data-label-swatches]').forEach(
        (swatches) => {
            const input = document.getElementById(
                swatches.dataset.labelSwatches ?? '',
            );

            if (!(input instanceof HTMLInputElement)) {
                return;
            }

            swatches
                .querySelectorAll<HTMLButtonElement>('[data-color]')
                .forEach((swatch) =>
                    swatch.addEventListener('click', () => {
                        input.value = swatch.dataset.color ?? input.value;
                        input.dispatchEvent(new Event('input'));
                    }),
                );
        },
    );
}
