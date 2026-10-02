import { eachWidget } from './each';

/*
 * {{ temp }} widgets show °C and °F. Clicking one swaps which comes first,
 * everywhere on the page, and that choice is remembered.
 */

let preferenceRead = false;

export function startTemperatures(root: ParentNode): () => void {
    return eachWidget<HTMLButtonElement>(
        root,
        '.widget-temp',
        (button, signal) => {
            if (!preferenceRead) {
                preferenceRead = true;

                try {
                    const saved = localStorage.getItem('site.temperature');

                    if (saved === 'c' || saved === 'f') {
                        document.documentElement.dataset.temperature = saved;
                    }
                } catch {
                    // Not remembered (private windows): the written unit comes first.
                }
            }

            button.addEventListener(
                'click',
                () => {
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
                },
                { signal },
            );
        },
    );
}
