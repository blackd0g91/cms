<?php

namespace App\Cms\Concerns;

use App\Models\Media;
use GdImage;
use Illuminate\Support\Facades\Storage;

/**
 * Drawing pictures with GD, for ShareImage and AppIcon: everything is drawn
 * SCALE times larger (a constant of the class using it) and scaled down
 * after, which smooths the edges GD would otherwise leave jagged.
 *
 * @phpstan-type Rgb array{int, int, int}
 * @phpstan-type Background array{type: string, from: string, to: string, angle: int}
 */
trait DrawsImages
{
    /**
     * A new image, filled with a color or transparent.
     *
     * @param  Rgb|null  $fill
     */
    protected function canvas(int $width, int $height, ?array $fill = null): GdImage
    {
        $image = imagecreatetruecolor(max(1, $width * self::SCALE), max(1, $height * self::SCALE));
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, $fill ? $this->color($image, $fill) : $this->color($image, [0, 0, 0], 0));
        imagealphablending($image, true);

        return $image;
    }

    /**
     * @param  Rgb  $rgb
     */
    protected function circle(GdImage $image, float $centerX, float $centerY, float $diameter, array $rgb): void
    {
        imagefilledellipse($image, (int) round($centerX * self::SCALE), (int) round($centerY * self::SCALE), (int) round($diameter * self::SCALE), (int) round($diameter * self::SCALE), $this->color($image, $rgb));
    }

    /**
     * @param  Rgb  $rgb
     */
    protected function roundedRect(GdImage $image, float $x, float $y, float $width, float $height, float $radius, array $rgb): void
    {
        [$x, $y, $width, $height, $radius] = array_map(fn (float $value) => (int) round($value * self::SCALE), [$x, $y, $width, $height, $radius]);
        $color = $this->color($image, $rgb);

        imagefilledrectangle($image, $x + $radius, $y, $x + $width - $radius - 1, $y + $height - 1, $color);
        imagefilledrectangle($image, $x, $y + $radius, $x + $width - 1, $y + $height - $radius - 1, $color);

        foreach ([[$x + $radius, $y + $radius], [$x + $width - $radius - 1, $y + $radius], [$x + $radius, $y + $height - $radius - 1], [$x + $width - $radius - 1, $y + $height - $radius - 1]] as [$cx, $cy]) {
            imagefilledellipse($image, $cx, $cy, $radius * 2, $radius * 2, $color);
        }
    }

    /**
     * How far a point is outside a rounded rectangle, negative inside it.
     */
    protected static function distance(float $pointX, float $pointY, float $x, float $y, float $width, float $height, float $radius): float
    {
        $dx = abs($pointX - $x - $width / 2) - $width / 2 + $radius;
        $dy = abs($pointY - $y - $height / 2) - $height / 2 + $radius;

        return sqrt(max($dx, 0) ** 2 + max($dy, 0) ** 2) + min(max($dx, $dy), 0) - $radius;
    }

    /**
     * A rounded rectangle filled like the logo background setting: one color,
     * or a linear gradient at an angle, as in CSS.
     *
     * @param  Background  $background
     */
    protected function backgroundRect(GdImage $image, float $x, float $y, float $width, float $height, float $radius, array $background): void
    {
        $from = self::hexToRgb($background['from']);

        if ($background['type'] !== 'gradient') {
            $this->roundedRect($image, $x, $y, $width, $height, $radius, $from);

            return;
        }

        $to = self::hexToRgb($background['to']);
        [$x, $y, $width, $height, $radius] = array_map(fn (float $value) => $value * self::SCALE, [$x, $y, $width, $height, $radius]);
        $angle = deg2rad($background['angle']);
        $length = abs($width * sin($angle)) + abs($height * cos($angle));

        for ($row = 0; $row < $height; $row++) {
            for ($column = 0; $column < $width; $column++) {
                if (self::distance($column + 0.5, $row + 0.5, 0, 0, $width, $height, $radius) > 0) {
                    continue;
                }

                $along = 0.5 + (($column - $width / 2) * sin($angle) - ($row - $height / 2) * cos($angle)) / $length;
                imagesetpixel($image, (int) ($x + $column), (int) ($y + $row), $this->color($image, $this->mix($from, $to, max(0, min(1, $along)))));
            }
        }
    }

    protected function image(GdImage $image, GdImage $source, float $x, float $y, float $width, float $height): void
    {
        imagecopyresampled($image, $source, (int) round($x * self::SCALE), (int) round($y * self::SCALE), 0, 0, (int) round($width * self::SCALE), (int) round($height * self::SCALE), imagesx($source), imagesy($source));
    }

    /**
     * @param  Rgb  $rgb
     */
    protected function color(GdImage $image, array $rgb, float $opacity = 1): int
    {
        // GD throws on anything outside its ranges.
        $channel = fn (int $value) => max(0, min(255, $value));

        return (int) imagecolorallocatealpha($image, $channel($rgb[0]), $channel($rgb[1]), $channel($rgb[2]), max(0, min(127, (int) round(127 * (1 - $opacity)))));
    }

    /**
     * @param  Rgb  $from
     * @param  Rgb  $to
     * @return Rgb
     */
    protected function mix(array $from, array $to, float $amount): array
    {
        return [
            (int) round($from[0] + ($to[0] - $from[0]) * $amount),
            (int) round($from[1] + ($to[1] - $from[1]) * $amount),
            (int) round($from[2] + ($to[2] - $from[2]) * $amount),
        ];
    }

    /**
     * @return Rgb
     */
    protected static function hexToRgb(string $hex): array
    {
        [$r, $g, $b] = array_map(hexdec(...), str_split(substr(ltrim($hex, '#'), 0, 6), 2));

        return [(int) $r, (int) $g, (int) $b];
    }

    /**
     * GD sizes text in points at 96 dpi.
     */
    protected function points(float $pixels): float
    {
        return $pixels * self::SCALE * 0.75;
    }

    protected function display(): string
    {
        return resource_path('fonts/Fraunces-SemiBold.ttf');
    }

    /**
     * An uploaded image as one GD can draw, unless it is an SVG (which GD
     * can not read) or can not be read.
     */
    protected function raster(?Media $media): ?GdImage
    {
        if (! $media || $media->isSvg()) {
            return null;
        }

        $contents = Storage::disk($media->disk)->get($media->path);
        $image = $contents === null ? false : @imagecreatefromstring($contents);

        return $image === false ? null : $image;
    }
}
