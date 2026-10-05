<?php

namespace App\Cms\Widgets;

/**
 * {{ temp:180c }} or {{ temp:350f }}: a temperature in both Celsius and
 * Fahrenheit, so recipes work for everyone. Clicking it swaps which comes
 * first (resources/js/site.ts), and that is remembered.
 */
class TemperatureWidget implements ReadsAsText, Widget
{
    /**
     * A number of degrees and C or F: "180c", "350 °F", "-18,5 C".
     */
    private const string PATTERN = '/^\s*(-?\d+(?:[.,]\d+)?)\s*(?:°)?\s*([cf])\s*$/iu';

    public function name(): string
    {
        return 'temp';
    }

    public function text(string $value): ?string
    {
        // render() also turns down temperatures that can not be.
        if ($this->render($value) === null || ! preg_match(self::PATTERN, $value, $match)) {
            return null;
        }

        return self::exact((float) str_replace(',', '.', $match[1])).' °'.strtoupper($match[2]);
    }

    public function render(string $value): ?string
    {
        if (! preg_match(self::PATTERN, $value, $match)) {
            return null;
        }

        $degrees = (float) str_replace(',', '.', $match[1]);
        $celsius = strtolower($match[2]) === 'c'
            ? $degrees
            : ($degrees - 32) * 5 / 9;

        if ($celsius < -273.15 || $celsius > 1000) {
            return null;
        }

        $fahrenheit = $celsius * 9 / 5 + 32;
        $writtenInCelsius = strtolower($match[2]) === 'c';

        // What was written stays exact. The conversion is rounded the way
        // recipes write it: to 5 degrees for oven temperatures, but to the
        // degree below that, where it matters (like the inside of meat).
        $oven = $celsius >= 120;
        $c = $writtenInCelsius ? self::exact($degrees) : self::rounded($celsius, $oven);
        $f = $writtenInCelsius ? self::rounded($fahrenheit, $oven) : self::exact($degrees);

        return '<button type="button" class="widget widget-temp" data-first="'.($writtenInCelsius ? 'c' : 'f').'" title="Swap °C and °F">'
            .'<span class="widget-temp-c">'.$c.' °C</span>'
            .'<span class="widget-temp-sep" aria-hidden="true">/</span>'
            .'<span class="widget-temp-f">'.$f.' °F</span>'
            .'</button>';
    }

    private static function exact(float $degrees): string
    {
        return rtrim(rtrim(number_format($degrees, 1, '.', ''), '0'), '.');
    }

    private static function rounded(float $degrees, bool $toFive): string
    {
        return (string) (int) ($toFive ? round($degrees / 5) * 5 : round($degrees));
    }
}
