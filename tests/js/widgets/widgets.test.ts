import { afterEach, describe, expect, it, vi } from 'vite-plus/test';
import { eachWidget } from '@/widgets/each';
import { startSpoilers } from '@/widgets/spoiler';
import { startTemperatures } from '@/widgets/temperature';

afterEach(() => {
    document.body.innerHTML = '';
    delete document.documentElement.dataset.temperature;
    localStorage.clear();
});

describe('eachWidget', () => {
    it('starts each element once, and stops them all', () => {
        document.body.innerHTML = '<p class="w"></p><p class="w"></p>';
        const stopOne = vi.fn();
        const start = vi.fn(() => stopOne);

        const stop = eachWidget(document, '.w', start);
        eachWidget(document, '.w', start);

        expect(start).toHaveBeenCalledTimes(2);

        stop();

        expect(stopOne).toHaveBeenCalledTimes(2);
        expect(
            document.querySelector<HTMLElement>('.w')!.dataset.widgetStarted,
        ).toBeUndefined();
    });

    it('removes the listeners it was given when stopped', () => {
        document.body.innerHTML = '<button class="w"></button>';
        const clicked = vi.fn();

        const stop = eachWidget<HTMLButtonElement>(
            document,
            '.w',
            (button, signal) => {
                button.addEventListener('click', clicked, { signal });
            },
        );

        document.querySelector('button')!.click();
        stop();
        document.querySelector('button')!.click();

        expect(clicked).toHaveBeenCalledTimes(1);
    });
});

describe('spoiler', () => {
    it('shows and hides on click', () => {
        document.body.innerHTML =
            '<button type="button" class="widget widget-spoiler" aria-expanded="false" title="Show"><span class="widget-spoiler-text">Rosebud</span></button>';
        startSpoilers(document);
        const spoiler = document.querySelector('button')!;

        spoiler.click();

        expect(spoiler.getAttribute('aria-expanded')).toBe('true');
        expect(spoiler.title).toBe('Hide');

        spoiler.click();

        expect(spoiler.getAttribute('aria-expanded')).toBe('false');
        expect(spoiler.title).toBe('Show');
    });
});

describe('temperature', () => {
    it('swaps which unit comes first, and remembers it', () => {
        document.body.innerHTML =
            '<button type="button" class="widget widget-temp" data-first="c"><span class="widget-temp-c">180 °C</span><span class="widget-temp-f">356 °F</span></button>';
        startTemperatures(document);
        const button = document.querySelector('button')!;

        button.click();

        expect(document.documentElement.dataset.temperature).toBe('f');
        expect(localStorage.getItem('site.temperature')).toBe('f');

        button.click();

        expect(document.documentElement.dataset.temperature).toBe('c');
    });
});
