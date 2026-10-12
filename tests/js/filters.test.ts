import { afterEach, beforeEach, describe, expect, it } from 'vite-plus/test';
import { startFilters } from '@/filters';

describe('startFilters', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <div data-filters="games">
                <button data-filter="" aria-pressed="true">All</button>
                <button data-filter="PC" aria-pressed="false">PC</button>
                <button data-filter="Mobile" aria-pressed="false">Mobile</button>
            </div>
            <ul id="games">
                <li data-filter-tags="PC">Pony Island</li>
                <li data-filter-tags="Mobile">The Big Con</li>
                <li data-filter-tags="PC|Mobile">Both</li>
            </ul>`;
        startFilters();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    const button = (name: string) =>
        [...document.querySelectorAll<HTMLButtonElement>('[data-filter]')].find(
            (element) => element.textContent === name,
        )!;
    const shown = () =>
        [...document.querySelectorAll<HTMLElement>('li')]
            .filter((item) => !item.hidden)
            .map((item) => item.textContent);

    it('shows only the items with the chosen tag', () => {
        button('Mobile').click();

        expect(shown()).toEqual(['The Big Con', 'Both']);
        expect(button('Mobile').getAttribute('aria-pressed')).toBe('true');
        expect(button('All').getAttribute('aria-pressed')).toBe('false');
    });

    it('shows everything again with the empty filter', () => {
        button('PC').click();
        button('All').click();

        expect(shown()).toEqual(['Pony Island', 'The Big Con', 'Both']);
    });
});
