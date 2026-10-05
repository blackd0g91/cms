<?php

namespace App\Cms\Widgets;

/**
 * {{ recipe-servings:4 }} ("Serves 4") or {{ recipe-servings:12 | cookies }}
 * ("Makes 12 cookies"): what a recipe makes, with − and + to change it.
 * Every {{ recipe-amount }} after it, up to the next one, changes along
 * (resources/js/widgets/recipe.ts). Without the script it is just the text.
 */
class RecipeServingsWidget implements ReadsAsText, Widget
{
    public const int MAX = 999;

    public function name(): string
    {
        return 'recipe-servings';
    }

    public function text(string $value): ?string
    {
        [$count, $unit] = array_pad(array_map('trim', explode('|', $value, 2)), 2, '');

        if ($this->render($value) === null) {
            return null;
        }

        return $unit !== '' ? 'Makes '.(int) $count." {$unit}" : 'Serves '.(int) $count;
    }

    public function render(string $value): ?string
    {
        [$count, $unit] = array_pad(array_map('trim', explode('|', $value, 2)), 2, '');

        if (! preg_match('/^\d{1,3}$/', $count) || (int) $count < 1) {
            return null;
        }

        return '<span class="widget widget-servings" data-servings="'.(int) $count.'" role="group" aria-label="Servings">'
            .'<span class="widget-servings-label">'.($unit !== '' ? 'Makes' : 'Serves').'</span>'
            .'<button type="button" class="widget-servings-less" aria-label="Fewer servings" hidden>−</button>'
            .'<output class="widget-servings-count" aria-live="polite">'.(int) $count.'</output>'
            .'<button type="button" class="widget-servings-more" aria-label="More servings" hidden>+</button>'
            .($unit !== '' ? '<span class="widget-servings-unit">'.e($unit).'</span>' : '')
            .'</span>';
    }
}
