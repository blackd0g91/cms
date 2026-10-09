import { afterEach, describe, expect, it } from 'vite-plus/test';
import { formatAmount, startRecipes } from '@/widgets/recipe';

describe('formatAmount', () => {
    it('keeps fractions as fractions', () => {
        expect(formatAmount(0.75, 'fraction')).toBe('¾');
        expect(formatAmount(1.5, 'fraction')).toBe('1½');
        expect(formatAmount(2.32, 'fraction')).toBe('2⅓');
    });

    it('rounds up to the next whole number when that is nearer', () => {
        expect(formatAmount(1.97, 'fraction')).toBe('2');
    });

    it('never writes zero', () => {
        expect(formatAmount(0.01, 'fraction')).toBe('⅛');
        expect(formatAmount(0.001, 'decimal')).toBe('0.01');
    });

    it('rounds decimals the way recipes write them', () => {
        expect(formatAmount(233.33, 'decimal')).toBe('233');
        expect(formatAmount(1.53, 'decimal')).toBe('1.5');
        expect(formatAmount(0.256, 'decimal')).toBe('0.26');
        expect(formatAmount(1.53, 'comma')).toBe('1,5');
    });
});

// As app/Cms/Widgets/RecipeServingsWidget.php and RecipeAmountWidget.php write them.
const servings = (count: number) =>
    `<span class="widget widget-servings" data-servings="${count}" role="group" aria-label="Servings">` +
    '<span class="widget-servings-label">Serves</span>' +
    '<button type="button" class="widget-servings-less" aria-label="Fewer servings" hidden>−</button>' +
    `<output class="widget-servings-count" aria-live="polite">${count}</output>` +
    '<button type="button" class="widget-servings-more" aria-label="More servings" hidden>+</button>' +
    '</span>';

const amount = (
    from: number,
    written: string,
    unit: string,
    style = 'decimal',
    to?: number,
) =>
    `<span class="widget widget-amount" data-amount="${from}" data-style="${style}"${to ? ` data-amount-to="${to}"` : ''}>` +
    `<span class="widget-amount-value">${written}</span> <span class="widget-amount-unit">${unit}</span>` +
    '</span>';

const values = () =>
    [...document.querySelectorAll('.widget-amount-value')].map(
        (value) => value.textContent,
    );

const click = (selector: string, index = 0) =>
    document.querySelectorAll<HTMLButtonElement>(selector)[index].click();

describe('recipe widgets', () => {
    let stop = () => {};

    afterEach(() => {
        stop();
        document.body.innerHTML = '';
    });

    it('scales the amounts below the servings', () => {
        document.body.innerHTML =
            servings(4) +
            amount(200, '200', 'g') +
            amount(0.5, '½', 'cup', 'fraction') +
            amount(1, '1', 'l', 'decimal', 2);
        stop = startRecipes(document);

        expect(
            document.querySelector<HTMLButtonElement>('.widget-servings-less')!
                .hidden,
        ).toBe(false);

        click('.widget-servings-more');
        click('.widget-servings-more');

        expect(
            document.querySelector('.widget-servings-count')!.textContent,
        ).toBe('6');
        expect(values()).toEqual(['300', '¾', '1.5–3']);

        const scaled = document.querySelector<HTMLElement>('.widget-amount')!;
        expect(scaled.hasAttribute('data-scaled')).toBe(true);
        expect(scaled.title).toBe('As written, for 4: 200 g');
    });

    it('shows the amounts as written again at the original servings', () => {
        document.body.innerHTML =
            servings(2) + amount(0.5, '1/2', 'cup', 'fraction');
        stop = startRecipes(document);

        click('.widget-servings-more');
        click('.widget-servings-less');

        expect(values()).toEqual(['1/2']);
        expect(
            document
                .querySelector('.widget-amount')!
                .hasAttribute('data-scaled'),
        ).toBe(false);
    });

    it('does not go below one serving', () => {
        document.body.innerHTML = servings(1) + amount(100, '100', 'g');
        stop = startRecipes(document);

        const less = document.querySelector<HTMLButtonElement>(
            '.widget-servings-less',
        )!;
        expect(less.disabled).toBe(true);

        less.click();

        expect(
            document.querySelector('.widget-servings-count')!.textContent,
        ).toBe('1');
    });

    it('only scales the amounts up to the next servings', () => {
        document.body.innerHTML =
            servings(2) +
            amount(100, '100', 'g') +
            servings(4) +
            amount(50, '50', 'ml');
        stop = startRecipes(document);

        click('.widget-servings-more', 0);

        expect(values()).toEqual(['150', '50']);
    });
});
