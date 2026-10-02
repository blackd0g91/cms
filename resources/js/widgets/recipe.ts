import { eachWidget } from './each';

/*
 * {{ recipe-servings }} and {{ recipe-amount }}: − and + change how many the
 * recipe makes, and every amount after it (up to the next servings) changes
 * along. The amounts' numbers come from RecipeAmountWidget::parse().
 */

const MAX_SERVINGS = 999;

// The fractions recipes are written with.
const FRACTIONS: [number, string][] = [
    [1 / 8, '⅛'],
    [1 / 4, '¼'],
    [1 / 3, '⅓'],
    [3 / 8, '⅜'],
    [1 / 2, '½'],
    [5 / 8, '⅝'],
    [2 / 3, '⅔'],
    [3 / 4, '¾'],
    [7 / 8, '⅞'],
];

/**
 * An amount for other servings, written like the original: fractions stay
 * fractions (¾ cup, not 0.75), and decimals are rounded the way recipes
 * write them (233 g, 1.5 kg, 0.25 l), with a comma if it had one.
 */
export const formatAmount = (
    value: number,
    // "fraction", "decimal" or "comma", from the amount's data-style.
    style: string,
): string => {
    if (style === 'fraction') {
        let whole = Math.floor(value);
        const rest = value - whole;
        let nearest = { distance: rest, text: '' };

        for (const [fraction, text] of FRACTIONS) {
            if (Math.abs(rest - fraction) < nearest.distance) {
                nearest = { distance: Math.abs(rest - fraction), text };
            }
        }

        if (1 - rest < nearest.distance) {
            whole += 1;
            nearest = { distance: 0, text: '' };
        }

        // Never "0 tsp": the smallest fraction instead.
        return whole === 0 ? nearest.text || '⅛' : `${whole}${nearest.text}`;
    }

    const digits = value >= 10 ? 0 : value >= 1 ? 1 : 2;
    const text = String(Number(value.toFixed(digits)) || 10 ** -digits);

    return style === 'comma' ? text.replace('.', ',') : text;
};

export function startRecipes(root: ParentNode): () => void {
    // In page order, so each amount belongs to the servings above it.
    const groups = new Map<HTMLElement, HTMLElement[]>();
    let current: HTMLElement[] | null = null;

    root.querySelectorAll<HTMLElement>(
        '.widget-servings[data-servings], .widget-amount[data-amount]',
    ).forEach((element) => {
        if (element.matches('.widget-servings')) {
            current = [];
            groups.set(element, current);
        } else {
            current?.push(element);
        }
    });

    return eachWidget<HTMLElement>(
        root,
        '.widget-servings[data-servings]',
        (widget, signal) => {
            const base = Number(widget.dataset.servings);
            const less = widget.querySelector<HTMLButtonElement>(
                '.widget-servings-less',
            )!;
            const more = widget.querySelector<HTMLButtonElement>(
                '.widget-servings-more',
            )!;
            const count = widget.querySelector<HTMLOutputElement>(
                '.widget-servings-count',
            )!;
            const amounts = (groups.get(widget) ?? []).map((element) => {
                const value = element.querySelector<HTMLElement>(
                    '.widget-amount-value',
                )!;

                return {
                    element,
                    value,
                    written: value.textContent ?? '',
                    // With the unit, for the tooltip of a changed amount.
                    whole: element.textContent?.trim() ?? '',
                };
            });
            let servings = base;

            const update = () => {
                const factor = servings / base;

                count.textContent = String(servings);
                less.disabled = servings <= 1;
                more.disabled = servings >= MAX_SERVINGS;

                amounts.forEach(({ element, value, written, whole }) => {
                    const style = element.dataset.style ?? 'decimal';
                    const from = Number(element.dataset.amount) * factor;
                    const to = element.dataset.amountTo
                        ? Number(element.dataset.amountTo) * factor
                        : null;

                    value.textContent =
                        factor === 1
                            ? written
                            : formatAmount(from, style) +
                              (to === null
                                  ? ''
                                  : `–${formatAmount(to, style)}`);
                    element.toggleAttribute('data-scaled', factor !== 1);
                    element.title =
                        factor === 1 ? '' : `As written, for ${base}: ${whole}`;
                });
            };

            less.hidden = false;
            more.hidden = false;
            update();

            less.addEventListener(
                'click',
                () => {
                    servings = Math.max(1, servings - 1);
                    update();
                },
                { signal },
            );

            more.addEventListener(
                'click',
                () => {
                    servings = Math.min(MAX_SERVINGS, servings + 1);
                    update();
                },
                { signal },
            );
        },
    );
}
