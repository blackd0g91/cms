import { afterEach, beforeEach, describe, expect, it } from 'vite-plus/test';
import { startSortables } from '@/sortable';

const item = (value: string) => `
    <li data-sortable-item>
        <input type="checkbox" name="widgets[]" value="${value}" checked>
        <span data-sortable-buttons hidden>
            <button type="button" data-move="up">Up</button>
            <button type="button" data-move="down">Down</button>
        </span>
    </li>`;

describe('sortable lists', () => {
    beforeEach(() => {
        document.body.innerHTML = `
            <form><ol data-sortable>${item('weather')}${item('picture')}${item('games')}</ol></form>`;
        startSortables();
    });

    afterEach(() => {
        document.body.innerHTML = '';
    });

    const order = () =>
        new FormData(document.querySelector('form')!).getAll('widgets[]');
    const button = (value: string, move: 'up' | 'down') =>
        document
            .querySelector(`input[value="${value}"]`)!
            .closest('li')!
            .querySelector<HTMLButtonElement>(`[data-move="${move}"]`)!;

    it('shows the buttons, the first unable to go up and the last down', () => {
        expect(
            document.querySelector<HTMLElement>('[data-sortable-buttons]')!
                .hidden,
        ).toBe(false);
        expect(button('weather', 'up').disabled).toBe(true);
        expect(button('weather', 'down').disabled).toBe(false);
        expect(button('games', 'down').disabled).toBe(true);
    });

    it('moves items, so the form sends them in the new order', () => {
        button('games', 'up').click();
        expect(order()).toEqual(['weather', 'games', 'picture']);

        button('weather', 'down').click();
        expect(order()).toEqual(['games', 'weather', 'picture']);

        expect(button('games', 'up').disabled).toBe(true);
        expect(button('picture', 'down').disabled).toBe(true);
    });

    it('keeps the focus on a button that can still be used', () => {
        button('picture', 'up').click();

        expect(document.activeElement).toBe(button('picture', 'down'));

        // Weather goes last, so on to its other button.
        button('weather', 'down').click();
        expect(document.activeElement).toBe(button('weather', 'up'));

        button('picture', 'down').click();
        expect(document.activeElement).toBe(button('picture', 'down'));
    });
});
