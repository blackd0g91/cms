/**
 * Notes read as they will once saved, while they are written (see
 * site/notes/form.blade.php): the site renders the markdown, the same way
 * as the saved note, a moment after typing stops. Changing a note, in its
 * dialog, shows it as the dialog opens. A switch shows the text alone,
 * both side by side, or the preview alone; the choice is kept in this
 * browser, for every note form.
 */

// How long typing has to pause before the preview catches up.
const WAIT = 250;

const EMPTY = 'The preview shows here as you write.';

export type View = 'write' | 'split' | 'preview';

const VIEW_KEY = 'site.notes.view';

const savedView = (): View => {
    try {
        const view = localStorage.getItem(VIEW_KEY);

        return view === 'write' || view === 'preview' ? view : 'split';
    } catch {
        return 'split';
    }
};

const saveView = (view: View) => {
    try {
        localStorage.setItem(VIEW_KEY, view);
    } catch {
        // Not kept: this page still switches.
    }
};

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

            const owner = form.closest('form') ?? form;
            const write = form.querySelector<HTMLElement>('[data-note-write]');

            const show = (view: View) => {
                // Side by side on wide screens; one above the other below.
                form.classList.toggle('lg:grid-cols-2', view === 'split');

                if (write) {
                    write.hidden = view === 'preview';
                }

                pane.hidden = view === 'write';
                owner
                    .querySelectorAll<HTMLElement>('[data-note-view]')
                    .forEach((button) =>
                        button.setAttribute(
                            'aria-pressed',
                            String(button.dataset.noteView === view),
                        ),
                    );

                // Not for the notes in closed dialogs: they wait to open.
                if (view !== 'write' && (!dialog || dialog.open)) {
                    void render();
                }
            };

            owner
                .querySelectorAll<HTMLElement>('[data-note-view]')
                .forEach((button) =>
                    button.addEventListener('click', () => {
                        const view = button.dataset.noteView as View;

                        saveView(view);
                        // Every note form on the page follows.
                        document.dispatchEvent(
                            new CustomEvent('note-view', { detail: view }),
                        );
                    }),
                );
            document.addEventListener('note-view', (event) =>
                show((event as CustomEvent<View>).detail),
            );
            owner
                .querySelectorAll<HTMLElement>('[data-note-views]')
                .forEach((views) => {
                    views.hidden = false;
                });

            if (text.value.trim() === '') {
                shown = '';
                showEmpty();
            }

            show(savedView());
        },
    );
}
