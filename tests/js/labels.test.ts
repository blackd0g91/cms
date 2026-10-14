import { afterEach, describe, expect, it } from 'vite-plus/test';
import { startLabelSwatches } from '@/labels';

describe('label swatches', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('sets the color square to the swatch picked', () => {
        document.body.innerHTML = `
            <input id="color" type="color" value="#000000">
            <div data-label-swatches="color">
                <button type="button" data-color="#16a34a"></button>
            </div>`;
        startLabelSwatches();

        document.querySelector<HTMLButtonElement>('[data-color]')!.click();

        expect(document.querySelector<HTMLInputElement>('#color')!.value).toBe(
            '#16a34a',
        );
    });
});
