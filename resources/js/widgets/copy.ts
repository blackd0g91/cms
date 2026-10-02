import { eachWidget } from './each';

/* {{ copy }} widgets: the button copies the text. */

export function startCopyButtons(root: ParentNode): () => void {
    return eachWidget<HTMLElement>(
        root,
        '.widget-copy[data-copy]',
        (widget, signal) => {
            const button = widget.querySelector<HTMLButtonElement>(
                '.widget-copy-button',
            );
            let timeout: number | undefined;

            button?.addEventListener(
                'click',
                async () => {
                    const text = widget.dataset.copy ?? '';

                    try {
                        await navigator.clipboard.writeText(text);
                    } catch {
                        // Without clipboard access (like over plain http), the old way.
                        const field = Object.assign(
                            document.createElement('textarea'),
                            { value: text },
                        );
                        document.body.append(field);
                        field.select();
                        document.execCommand('copy');
                        field.remove();
                    }

                    widget.dataset.copied = '';
                    window.clearTimeout(timeout);
                    timeout = window.setTimeout(
                        () => delete widget.dataset.copied,
                        1500,
                    );
                },
                { signal },
            );

            return () => window.clearTimeout(timeout);
        },
    );
}
