<?php

namespace App\Cms;

use App\Cms\Concerns\DrawsImages;
use GdImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The site's icon as an app: for push notifications, phones' home screens
 * and the app manifest. A square with the logo set on the Settings page in
 * the middle, on its background when it has one, or the first letter of the
 * site's name in a badge, like the site's header. A logo GD can not read (an
 * SVG) gives way to the site icon, then to the letter. Drawn when first asked
 * for, then kept on disk until the settings it shows change.
 *
 * @phpstan-type Rgb array{int, int, int}
 */
class AppIcon
{
    use DrawsImages;

    /**
     * The sizes it is drawn at: for notifications and small icons, and for
     * home screens and splash screens.
     */
    public const array SIZES = [192, 512];

    /**
     * Change it to redraw every icon, after changing how they look.
     */
    private const int DESIGN = 1;

    private const int SCALE = 2;

    private const string FOLDER = 'app-icons';

    // The site's light theme colors (see resources/css/site.css).
    private const array CARD = [255, 252, 245];

    private const array INK = [34, 29, 23];

    private const array PAPER = [245, 239, 227];

    public function __construct(private Settings $settings) {}

    /**
     * A short fingerprint of everything drawn on the icon, so its URL and
     * file change whenever the picture does.
     */
    public function version(): string
    {
        return substr(md5((string) json_encode([
            self::DESIGN,
            $this->settings->siteName(),
            $this->settings->get('logo_id'),
            $this->settings->get('favicon_id'),
            $this->settings->get('logo_background'),
        ])), 0, 12);
    }

    public function url(int $size = 192): string
    {
        return route('app-icon', ['size' => $size, 'v' => $this->version()]);
    }

    /**
     * The icon file at $size, drawn first when this version of it does not
     * exist yet.
     */
    public function path(int $size): string
    {
        $disk = Storage::disk('local');
        $path = $disk->path(self::FOLDER."/{$size}-{$this->version()}.png");

        if (! is_file($path)) {
            $disk->makeDirectory(self::FOLDER);

            // Written aside and moved in place, so a request arriving
            // meanwhile never reads half an image.
            $temporary = "{$path}.".Str::random(8);
            file_put_contents($temporary, $this->render($size));
            rename($temporary, $path);

            // Older versions at this size.
            File::delete(array_diff(glob($disk->path(self::FOLDER."/{$size}-*.png")) ?: [], [$path]));
        }

        return $path;
    }

    /**
     * Draw the icon at $size, as PNG data.
     */
    public function render(int $size): string
    {
        $background = $this->settings->all()['logo_background'];
        $background = in_array($background['type'] ?? null, ['solid', 'gradient'], true) ? $background : null;
        $mark = $this->raster($this->settings->logo()) ?? $this->raster($this->settings->favicon());

        $image = $this->canvas($size, $size, $background ? null : ($mark ? self::CARD : self::INK));

        if ($background) {
            $this->backgroundRect($image, 0, 0, $size, $size, 0, $background);
        }

        if ($mark) {
            $this->drawCentered($image, $mark, $size, $size * ($background ? 0.62 : 0.72));
        } else {
            $this->drawLetter($image, $size, $background ? [255, 255, 255] : self::PAPER);
        }

        $small = imagecreatetruecolor(max(1, $size), max(1, $size));
        imagealphablending($small, false);
        imagesavealpha($small, true);
        imagecopyresampled($small, $image, 0, 0, 0, 0, $size, $size, imagesx($image), imagesy($image));

        ob_start();
        imagepng($small, null, 9);

        return (string) ob_get_clean();
    }

    /**
     * The picture in the middle, as large as fits in a $box wide square,
     * keeping its shape.
     */
    private function drawCentered(GdImage $image, GdImage $mark, int $size, float $box): void
    {
        $scale = min($box / imagesx($mark), $box / imagesy($mark));
        $width = imagesx($mark) * $scale;
        $height = imagesy($mark) * $scale;

        $this->image($image, $mark, ($size - $width) / 2, ($size - $height) / 2, $width, $height);
    }

    /**
     * The first letter of the site's name, in the middle.
     *
     * @param  Rgb  $color
     */
    private function drawLetter(GdImage $image, int $size, array $color): void
    {
        $letter = Str::upper(Str::substr(ShareImage::printable($this->settings->siteName()), 0, 1)) ?: '·';
        $points = $this->points($size * 0.55);
        $box = imagettfbbox($points, 0, $this->display(), $letter) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        $width = $box[2] - $box[0];
        $height = $box[1] - $box[7];

        imagettftext(
            $image,
            $points,
            0,
            (int) round(($size * self::SCALE - $width) / 2 - $box[0]),
            (int) round(($size * self::SCALE + $height) / 2 - $box[1]),
            $this->color($image, $color),
            $this->display(),
            $letter,
        );
    }
}
