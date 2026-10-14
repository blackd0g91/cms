/**
 * Notes read as they will once saved, while they are written (see
 * site/notes/form.blade.php): the site renders the markdown, the same way
 * as the saved note, a moment after typing stops. Changing a note, in its
 * dialog, shows it as the dialog opens.
 */

// How long typing has to pause before the preview catches up.
const WAIT = 250;

const EMPTY = 'The preview shows here as you write.';

export function startNotePreviews(root: ParentNode = document) {
    root.querySelectorAll<HTMLElement>('[data-note-preview]').forEach(
        (form) => {
            const url = form.dataset.notePreview ?? '';
            const text = form.querySelector('textarea');
            const pane = form.querySelector<HTMLElement>(
                '[data-note-preview-pane]',
            );
            const output = form.querySelector<HTMLElement>(
                '[data-note-preview-output]',
            );

            if (!url || !text || !pane || !output) {
                return;
            }

            let timer = 0;
            let request: AbortController | null = null;
            let shown: string | null = null;

            const showEmpty = () => {
                output.replaceChildren();
                const hint = document.createElement('p');
                hint.className = 'text-muted';
                hint.textContent = EMPTY;
                output.append(hint);
            };

            const render = async () => {
                const body = text.value;

                if (body === shown) {
                    return;
                }

                shown = body;
                request?.abort();

                if (body.trim() === '') {
                    showEmpty();

                    return;
                }

                request = new AbortController();

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN':
                                document.body.dataset.csrfToken ?? '',
                        },
                        body: JSON.stringify({ body }),
                        signal: request.signal,
                    });

                    if (!response.ok) {
                        throw new Error(`Status ${response.status}`);
                    }

                    // The site's own markdown, which never lets html through.
                    output.innerHTML = (
                        (await response.json()) as { html: string }
                    ).html;
                } catch (error) {
                    if ((error as Error).name !== 'AbortError') {
                        // Tried again on the next change.
                        shown = null;
                    }
                }
            };

            text.addEventListener('input', () => {
                window.clearTimeout(timer);
                timer = window.setTimeout(render, WAIT);
            });
            text.addEventListener('focus', render, { once: true });

            const dialog = text.closest('dialog');
            dialog?.addEventListener('dialog-open', render);

            // Already open, like when sent back with an error.
            if (dialog?.open) {
                void render();
            }

            pane.hidden = false;

            if (text.value.trim() === '') {
                shown = '';
                showEmpty();
            }
        },
    );
}
