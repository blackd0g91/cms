import { eachWidget } from './each';

/* {{ spoiler }}: tap to show, tap again to hide. */

export function startSpoilers(root: ParentNode): () => void {
    return eachWidget<HTMLButtonElement>(
        root,
        '.widget-spoiler',
        (spoiler, signal) => {
            spoiler.addEventListener(
                'click',
                () => {
                    const shown =
                        spoiler.getAttribute('aria-expanded') !== 'true';

                    spoiler.setAttribute('aria-expanded', String(shown));
                    spoiler.title = shown ? 'Hide' : 'Show';
                },
                { signal },
            );
        },
    );
}
