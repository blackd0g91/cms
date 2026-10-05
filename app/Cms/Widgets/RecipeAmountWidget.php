<?php

namespace App\Cms\Widgets;

/**
 * {{ recipe-amount:200 g }}, {{ recipe-amount:1 1/2 cups }},
 * {{ recipe-amount:½ tsp }} or {{ recipe-amount:2-3 }}: an amount in a recipe,
 * shown as written, that changes with the {{ recipe-servings }} above it. The
 * number is read here, so the script (resources/js/widgets/recipe.ts) only
 * multiplies it.
 */
class RecipeAmountWidget implements ReadsAsText, Widget
{
    private const int MAX = 100_000;

    private const array FRACTIONS = [
        '½' => 1 / 2, '⅓' => 1 / 3, '⅔' => 2 / 3, '¼' => 1 / 4, '¾' => 3 / 4,
        '⅕' => 1 / 5, '⅖' => 2 / 5, '⅗' => 3 / 5, '⅘' => 4 / 5, '⅙' => 1 / 6,
        '⅚' => 5 / 6, '⅛' => 1 / 8, '⅜' => 3 / 8, '⅝' => 5 / 8, '⅞' => 7 / 8,
    ];

    public function name(): string
    {
        return 'recipe-amount';
    }

    public function text(string $value): ?string
    {
        $amount = self::parse($value);

        return $amount === null ? null : trim("{$amount['number']} {$amount['unit']}");
    }

    public function render(string $value): ?string
    {
        $amount = self::parse($value);

        if ($amount === null) {
            return null;
        }

        return '<span class="widget widget-amount" data-amount="'.self::attribute($amount['from']).'"'
            .($amount['to'] !== null ? ' data-amount-to="'.self::attribute($amount['to']).'"' : '')
            .' data-style="'.$amount['style'].'">'
            .'<span class="widget-amount-value">'.e($amount['number']).'</span>'
            .($amount['unit'] !== '' ? ' <span class="widget-amount-unit">'.e($amount['unit']).'</span>' : '')
            .'</span>';
    }

    /**
     * The amount as numbers, the number as written, what follows it (the
     * unit) and how to write it when scaled: as a fraction, or as a decimal
     * with a point or a comma, like it was written.
     *
     * @return array{from: float, to: float|null, number: string, unit: string, style: 'fraction'|'decimal'|'comma'}|null
     */
    public static function parse(string $value): ?array
    {
        $fractions = implode('', array_keys(self::FRACTIONS));
        // 1 1/2, 1/2, 1½, ½, 1.5, 1,5 or 2
        $number = "(?:\d+\s+\d+/\d+|\d+/\d+|\d+\s*[{$fractions}]|[{$fractions}]|\d+(?:[.,]\d+)?)";

        if (! preg_match("#^\s*(?<number>(?<from>{$number})(?:\s*[-–]\s*(?<to>{$number}))?)\s*(?<unit>.*?)\s*$#u", $value, $match)) {
            return null;
        }

        $from = self::number($match['from']);
        // Empty without a range: the unit's group after it always takes part.
        $to = $match['to'] !== '' ? self::number($match['to']) : null;

        if ($from === null || $from <= 0 || $from > self::MAX || ($to !== null && ($to < $from || $to > self::MAX))) {
            return null;
        }

        return [
            'from' => $from,
            'to' => $to,
            'number' => $match['number'],
            'unit' => $match['unit'],
            'style' => match (true) {
                (bool) preg_match("/[\/{$fractions}]/u", $match['number']) => 'fraction',
                str_contains($match['number'], ',') => 'comma',
                default => 'decimal',
            },
        ];
    }

    private static function number(string $text): ?float
    {
        $text = trim($text);

        if (preg_match('/^(?:(\d+)\s+)?(\d+)\/(\d+)$/', $text, $match)) {
            return (int) $match[3] === 0 ? null : (int) $match[1] + (int) $match[2] / (int) $match[3];
        }

        foreach (self::FRACTIONS as $character => $fraction) {
            if (str_ends_with($text, $character)) {
                return (int) trim(substr($text, 0, -strlen($character))) + $fraction;
            }
        }

        return (float) str_replace(',', '.', $text);
    }

    private static function attribute(float $number): string
    {
        return rtrim(rtrim(sprintf('%.6F', $number), '0'), '.');
    }
}
