import { afterEach, beforeEach, describe, expect, it } from 'vite-plus/test';
import { startDialogs } from '@/dialogs';

describe('startDialogs', () => {
    let opener: HTMLAnchorElement;
    let dialog: HTMLDialogElement;

    beforeEach(() => {
        document.body.innerHTML = `
            <a href="https://commons.wikimedia.org/" data-dialog-open="picture">Picture</a>
            <dialog id="picture"><button data-dialog-close>Close</button></dialog>`;
        opener = document.querySelector('a')!;
        dialog = document.querySelector('dialog')!;
        startDialogs();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('opens the dialog instead of following the link', () => {
        const click = new MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        });
        opener.dispatchEvent(click);

        expect(dialog.open).toBe(true);
        expect(click.defaultPrevented).toBe(true);
    });

    it('still follows the link to open it in a new tab', () => {
        const click = new MouseEvent('click', {
            bubbles: true,
            cancelable: true,
            ctrlKey: true,
        });
        opener.dispatchEvent(click);

        expect(dialog.open).toBe(false);
        expect(click.defaultPrevented).toBe(false);
    });

    it('closes from its close button', () => {
        opener.click();
        dialog.querySelector<HTMLButtonElement>('[data-dialog-close]')!.click();

        expect(dialog.open).toBe(false);
    });

    it('leaves openers without a dialog alone', () => {
        document.body.innerHTML =
            '<a href="/" data-dialog-open="nope">Nope</a>';
        startDialogs();
        const click = new MouseEvent('click', {
            bubbles: true,
            cancelable: true,
        });
        document.querySelector('a')!.dispatchEvent(click);

        expect(click.defaultPrevented).toBe(false);
    });
});
