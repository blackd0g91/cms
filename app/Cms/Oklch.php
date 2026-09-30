<?php

namespace App\Cms;

/**
 * A color in OKLCH, the space the site's accent colors are defined in (see
 * resources/css/site.css), for drawing them outside the browser.
 */
final readonly class Oklch
{
    public function __construct(
        public float $lightness,
        public float $chroma,
        public float $hue,
    ) {}

    /**
     * @param  string  $hex  A color like #c2410c
     */
    public static function fromHex(string $hex): self
    {
        [$r, $g, $b] = array_map(
            fn (string $channel) => self::toLinear(hexdec($channel) / 255),
            str_split(substr(ltrim($hex, '#'), 0, 6), 2),
        );

        // Linear sRGB to OKLab, from https://bottosson.github.io/posts/oklab/
        $l = (0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b) ** (1 / 3);
        $m = (0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b) ** (1 / 3);
        $s = (0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b) ** (1 / 3);

        $lightness = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $a = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $bb = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;

        return new self($lightness, sqrt($a ** 2 + $bb ** 2), fmod(rad2deg(atan2($bb, $a)) + 360, 360));
    }

    public function withLightness(float $lightness): self
    {
        return new self($lightness, $this->chroma, $this->hue);
    }

    /**
     * The color in sRGB, with channels from 0 to 255. Colors outside sRGB are
     * clipped to it.
     *
     * @return array{int, int, int}
     */
    public function toRgb(): array
    {
        $a = $this->chroma * cos(deg2rad($this->hue));
        $b = $this->chroma * sin(deg2rad($this->hue));

        $l = ($this->lightness + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($this->lightness - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($this->lightness - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;

        return [
            self::toChannel(4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s),
            self::toChannel(-1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s),
            self::toChannel(-0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s),
        ];
    }

    private static function toLinear(float $value): float
    {
        return $value <= 0.04045 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
    }

    private static function toChannel(float $linear): int
    {
        $linear = max(0.0, min(1.0, $linear));
        $value = $linear <= 0.0031308 ? 12.92 * $linear : 1.055 * $linear ** (1 / 2.4) - 0.055;

        return (int) round($value * 255);
    }
}
