import {
    afterEach,
    beforeEach,
    describe,
    expect,
    it,
    vi,
} from 'vite-plus/test';
import { startNotePreviews } from '@/notes';

describe('note previews', () => {
    const fetchMock = vi.fn();

    beforeEach(() => {
        vi.useFakeTimers();
        vi.stubGlobal('fetch', fetchMock);
        fetchMock.mockReset();
        fetchMock.mockImplementation(
            async (_url: string, init: RequestInit) => ({
                ok: true,
                json: async () => ({
                    html: `<p>${JSON.parse(init.body as string).body.toUpperCase()}</p>`,
                }),
            }),
        );
        document.body.dataset.csrfToken = 'the-token';
        document.body.innerHTML = `
            <form><div data-note-preview="/me/notes/preview">
                <textarea name="body"></textarea>
                <div data-note-preview-pane hidden><div data-note-preview-output></div></div>
            </div></form>`;
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
        document.body.innerHTML = '';
    });

    const text = () => document.querySelector('textarea')!;
    const output = () =>
        document.querySelector<HTMLElement>('[data-note-preview-output]')!;
    const type = (value: string) => {
        text().value = value;
        text().dispatchEvent(new Event('input'));
    };

    it('shows the pane, saying what it is for while empty', () => {
        startNotePreviews();

        expect(
            document.querySelector<HTMLElement>('[data-note-preview-pane]')!
                .hidden,
        ).toBe(false);
        expect(output().textContent).toContain('The preview shows here');
    });

    it('renders once typing pauses, asking the site', async () => {
        startNotePreviews();

        type('h');
        type('hi');
        await vi.advanceTimersByTimeAsync(300);

        expect(fetchMock).toHaveBeenCalledOnce();
        const [url, init] = fetchMock.mock.calls[0];
        expect(url).toBe('/me/notes/preview');
        expect((init.headers as Record<string, string>)['X-CSRF-TOKEN']).toBe(
            'the-token',
        );
        expect(output().innerHTML).toBe('<p>HI</p>');
    });

    it('renders text already there as soon as it shows', async () => {
        text().value = 'saved text';
        startNotePreviews();
        await vi.advanceTimersByTimeAsync(0);

        expect(output().innerHTML).toBe('<p>SAVED TEXT</p>');
    });

    it('renders a note being changed as its dialog opens', async () => {
        document.body.innerHTML = `
            <dialog><div data-note-preview="/me/notes/preview">
                <textarea name="body">in a dialog</textarea>
                <div data-note-preview-pane hidden><div data-note-preview-output></div></div>
            </div></dialog>`;
        startNotePreviews();

        document
            .querySelector('dialog')!
            .dispatchEvent(new Event('dialog-open'));
        await vi.advanceTimersByTimeAsync(0);

        expect(output().innerHTML).toBe('<p>IN A DIALOG</p>');
    });

    it('switches between the text, both, and the preview, for every form', async () => {
        localStorage.clear();
        document.body.innerHTML = ['a', 'b']
            .map(
                (id) => `
            <form id="${id}">
                <div data-note-views hidden>
                    <button type="button" data-note-view="write"></button>
                    <button type="button" data-note-view="split"></button>
                    <button type="button" data-note-view="preview"></button>
                </div>
                <div data-note-preview="/me/notes/preview">
                    <div data-note-write><textarea name="body">text ${id}</textarea></div>
                    <div data-note-preview-pane hidden><div data-note-preview-output></div></div>
                </div>
            </form>`,
            )
            .join('');
        startNotePreviews();

        const form = (id: string) => document.getElementById(id)!;
        const write = (id: string) =>
            form(id).querySelector<HTMLElement>('[data-note-write]')!;
        const pane = (id: string) =>
            form(id).querySelector<HTMLElement>('[data-note-preview-pane]')!;

        // Both, side by side, to start with.
        expect(
            form('a').querySelector<HTMLElement>('[data-note-views]')!.hidden,
        ).toBe(false);
        expect(pane('a').hidden).toBe(false);
        expect(
            form('a')
                .querySelector('[data-note-preview]')!
                .classList.contains('lg:grid-cols-2'),
        ).toBe(true);

        form('a')
            .querySelector<HTMLButtonElement>('[data-note-view="preview"]')!
            .click();
        await vi.advanceTimersByTimeAsync(0);

        for (const id of ['a', 'b']) {
            expect(write(id).hidden).toBe(true);
            expect(pane(id).hidden).toBe(false);
        }
        expect(output().innerHTML).toBe('<p>TEXT A</p>');
        expect(
            form('b')
                .querySelector('[data-note-view="preview"]')!
                .getAttribute('aria-pressed'),
        ).toBe('true');

        form('b')
            .querySelector<HTMLButtonElement>('[data-note-view="write"]')!
            .click();
        expect(pane('a').hidden).toBe(true);
        expect(write('a').hidden).toBe(false);
        expect(localStorage.getItem('site.notes.view')).toBe('write');
    });
});
