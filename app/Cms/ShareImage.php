<?php

namespace App\Cms;

use App\Cms\Concerns\DrawsImages;
use App\Models\Post;
use GdImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The picture shown in link previews of posts without a thumbnail: the title
 * on paper beside the lettered placeholder in the template's accent color,
 * like the post page. Drawn when first asked for, then kept on disk until
 * something shown on it changes.
 *
 * @phpstan-type Rgb array{int, int, int}
 * @phpstan-type Background array{type: string, from: string, to: string, angle: int}
 */
class ShareImage
{
    use DrawsImages;

    public const int WIDTH = 1200;

    public const int HEIGHT = 630;

    /**
     * Change it to redraw every image, after changing how they look.
     */
    private const int DESIGN = 2;

    /**
     * Everything is drawn this many times larger and then scaled down, which
     * smooths the edges GD would otherwise leave jagged.
     */
    private const int SCALE = 2;

    private const string FOLDER = 'share-images';

    private const int PADDING = 80;

    /**
     * The lettered placeholder: its size, the card colored ring around it
     * and its tilt, as on the post page.
     */
    private const int PLACEHOLDER = 280;

    private const int RING = 9;

    private const int TILT = 3;

    /**
     * Title sizes to try, largest first, and the most lines it may take.
     */
    private const array TITLE_SIZES = [72, 64, 56, 50, 44];

    private const int TITLE_LINES = 4;

    // The site's light theme colors (see resources/css/site.css).
    private const array PAPER = [245, 239, 227];

    private const array CARD = [255, 252, 245];

    private const array INK = [34, 29, 23];

    private const array MUTED = [123, 111, 95];

    public function __construct(private Settings $settings) {}

    /**
     * A short fingerprint of everything drawn on the post's image, so its URL
     * and file change whenever the picture does.
     */
    public function version(Post $post): string
    {
        return substr(md5((string) json_encode([
            self::DESIGN,
            $post->title,
            $post->template->name,
            $post->template->accentRgb(),
            $this->details($post),
            $this->settings->siteName(),
            $this->settings->get('logo_id'),
            $this->settings->get('logo_background'),
        ])), 0, 12);
    }

    public function url(Post $post): string
    {
        return route('site.post.share-image', [$post->template, $post, 'v' => $this->version($post)]);
    }

    /**
     * The image file, drawn first when this version of it does not exist yet.
     */
    public function path(Post $post): string
    {
        $disk = Storage::disk('local');
        $path = $disk->path(self::FOLDER."/{$post->id}-{$this->version($post)}.png");

        if (! is_file($path)) {
            $disk->makeDirectory(self::FOLDER);

            // Written aside and moved in place, so a request arriving
            // meanwhile never reads half an image.
            $temporary = "{$path}.".Str::random(8);
            file_put_contents($temporary, $this->render($post));
            rename($temporary, $path);

            $this->forget($post, except: $path);
        }

        return $path;
    }

    /**
     * Delete the post's images, of any version but $except.
     */
    public function forget(Post $post, ?string $except = null): void
    {
        $files = glob(Storage::disk('local')->path(self::FOLDER."/{$post->id}-*.png")) ?: [];

        File::delete(array_diff($files, [$except]));
    }

    /**
     * Draw the image, as PNG data.
     */
    public function render(Post $post): string
    {
        $image = $this->canvas(self::WIDTH, self::HEIGHT, self::PAPER);
        $accent = $post->template->accentRgb();
        $title = self::printable($post->title);

        $this->drawPlaceholder($image, $title, $accent);

        // Along the top, as above a post's title: its template and when it was published.
        $details = $this->details($post);
        $detailsWidth = $this->monoWidth($details, 24);
        $this->text($image, $this->mono(), 24, self::WIDTH - self::PADDING - $detailsWidth, 108, self::MUTED, $details, tracking: 1.2);

        $this->circle($image, self::PADDING + 8, 99, 16, $accent);
        $templateName = $this->truncate(Str::upper(self::printable($post->template->name)), fn (string $text) => $this->monoWidth($text, 24, 2.4), self::WIDTH - 2 * self::PADDING - $detailsWidth - 72);
        $this->text($image, $this->mono(), 24, self::PADDING + 32, 108, self::MUTED, $templateName, tracking: 2.4);

        $this->drawTitle($image, $title, top: 150, bottom: 470, width: self::WIDTH - 2 * self::PADDING - self::PLACEHOLDER - 64);
        $this->drawBrand($image, top: 494);

        $small = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagecopyresampled($small, $image, 0, 0, 0, 0, self::WIDTH, self::HEIGHT, imagesx($image), imagesy($image));

        ob_start();
        imagepng($small, null, 9);

        return (string) ob_get_clean();
    }

    /**
     * The text without emoji, which the fonts do not have.
     */
    public static function printable(string $text): string
    {
        return Str::squish((string) preg_replace(
            '/\p{Emoji_Presentation}|\p{Extended_Pictographic}\x{FE0F}|[\x{FE0E}\x{FE0F}\x{200D}\x{20E3}\x{E0020}-\x{E007F}]/u',
            '',
            $text,
        ));
    }

    /**
     * The date and reading time, as shown below a post's title.
     */
    private function details(Post $post): string
    {
        return collect([$post->published_at?->format('F j, Y'), "{$post->readingMinutes()} min read"])
            ->filter()
            ->implode(' · ');
    }

    /**
     * The title, in the largest size that fits, centered between $top and $bottom.
     */
    private function drawTitle(GdImage $image, string $title, int $top, int $bottom, int $width): void
    {
        // When no size fits, the smallest is used and the title cut short.
        $lines = [];
        $size = self::TITLE_SIZES[0];

        foreach (self::TITLE_SIZES as $size) {
            $lines = $this->wrap($title, $this->display(), $size, $width);

            if (count($lines) <= self::TITLE_LINES) {
                break;
            }
        }

        if (count($lines) > self::TITLE_LINES) {
            $lines = array_slice($lines, 0, self::TITLE_LINES);
            $lines[] = $this->truncate(array_pop($lines).'…', fn (string $text) => $this->textWidth($this->display(), $size, $text), $width);
        }

        $lineHeight = $size * 1.1;
        $firstLine = $top + ($bottom - $top - count($lines) * $lineHeight) / 2;
        $capHeight = $size * 0.7;

        foreach ($lines as $i => $line) {
            $this->text($image, $this->display(), $size, self::PADDING, $firstLine + $i * $lineHeight + ($lineHeight + $capHeight) / 2, self::INK, $line);
        }
    }

    /**
     * The title's first letter on a tilted card with a soft shadow.
     *
     * @param  Rgb  $accent
     */
    private function drawPlaceholder(GdImage $image, string $title, array $accent): void
    {
        $outer = self::PLACEHOLDER + 2 * self::RING;
        $margin = 64;
        $centerX = self::WIDTH - self::PADDING - $outer / 2;
        $centerY = self::HEIGHT / 2;

        // Drawn flat with room for the shadow, then tilted as a whole.
        $layer = $this->canvas($outer + 2 * $margin, $outer + 2 * $margin);

        // Tailwind's shadow-lg, scaled from the post page's 128px thumbnail.
        $this->shadow($layer, $margin, $margin, $outer, $outer, 36 + self::RING, [[23, 35, -7, 0.12], [9, 14, -9, 0.12]]);

        $this->roundedRect($layer, $margin, $margin, $outer, $outer, 36 + self::RING, self::CARD);
        $this->roundedRect($layer, $margin + self::RING, $margin + self::RING, self::PLACEHOLDER, self::PLACEHOLDER, 36, $this->mix(self::PAPER, $accent, 0.14));

        $letter = Str::upper(Str::substr($title, 0, 1));

        if ($letter !== '') {
            $size = self::PLACEHOLDER * 0.52;
            $box = imagettfbbox($this->points($size), 0, $this->display(), $letter) ?: [0, 0, 0, 0, 0, 0, 0, 0];
            $left = $margin + self::RING + (self::PLACEHOLDER - ($box[2] - $box[0]) / self::SCALE) / 2 - $box[0] / self::SCALE;
            $baseline = $margin + self::RING + (self::PLACEHOLDER - ($box[1] - $box[7]) / self::SCALE) / 2 - $box[7] / self::SCALE;
            $this->text($layer, $this->display(), $size, $left, $baseline, $accent, $letter);
        }

        $layer = imagerotate($layer, self::TILT, $this->color($layer, [0, 0, 0], 0)) ?: $layer;
        imagecopy($image, $layer, (int) ($centerX * self::SCALE - imagesx($layer) / 2), (int) ($centerY * self::SCALE - imagesy($layer) / 2), 0, 0, imagesx($layer), imagesy($layer));
    }

    /**
     * The site's logo (or its first letter in a badge) and name, like the
     * site's header.
     */
    private function drawBrand(GdImage $image, int $top): void
    {
        $height = 56;
        $background = $this->settings->all()['logo_background'];
        $background = in_array($background['type'] ?? null, ['solid', 'gradient'], true) ? $background : null;
        $siteName = self::printable($this->settings->siteName());
        $logo = $this->logo();

        if ($logo && $background) {
            $width = min(144, (int) round(imagesx($logo) * 40 / imagesy($logo)));
            $this->backgroundRect($image, self::PADDING, $top, max($height, $width + 16), $height, 17, $background);
            $this->image($image, $logo, self::PADDING + (max($height, $width + 16) - $width) / 2, $top + 8, $width, 40);
            $markWidth = max($height, $width + 16);
        } elseif ($logo) {
            $markWidth = min(160, (int) round(imagesx($logo) * $height / imagesy($logo)));
            $this->image($image, $logo, self::PADDING, $top, $markWidth, $height);
        } else {
            $markWidth = $height;

            if ($background) {
                $this->backgroundRect($image, self::PADDING, $top, $height, $height, $height / 2, $background);
            } else {
                $this->circle($image, self::PADDING + $height / 2, $top + $height / 2, $height, self::INK);
            }

            $letter = Str::upper(Str::substr($siteName, 0, 1));
            $box = imagettfbbox($this->points(28), 0, $this->display(), $letter) ?: [0, 0, 0, 0, 0, 0, 0, 0];
            $this->text($image, $this->display(), 28, self::PADDING + ($height - ($box[2] - $box[0]) / self::SCALE) / 2 - $box[0] / self::SCALE, $top + $height / 2 + 28 * 0.35, $background ? [255, 255, 255] : self::PAPER, $letter);
        }

        $left = self::PADDING + $markWidth + 18;
        $name = $this->truncate($siteName, fn (string $text) => $this->textWidth($this->display(), 34, $text), self::WIDTH - $left - self::PADDING);
        $this->text($image, $this->display(), 34, $left, $top + $height / 2 + 34 * 0.35, self::INK, $name);
    }

    /**
     * The logo as an image GD can draw, when there is one that can be read.
     */
    private function logo(): ?GdImage
    {
        return $this->raster($this->settings->logo());
    }

    /**
     * Break text into lines no wider than $width, after spaces or hyphens,
     * splitting words that do not fit on a line by themselves.
     *
     * @return list<string>
     */
    private function wrap(string $text, string $font, float $size, float $width): array
    {
        $fits = fn (string $text) => $this->textWidth($font, $size, rtrim($text)) <= $width;
        $lines = [];
        $line = '';

        // Each piece keeps the space or hyphen that ends it.
        foreach (preg_split('/(?<=\s)|(?<=-)(?=\S)/u', $text, flags: PREG_SPLIT_NO_EMPTY) ?: [] as $piece) {
            if ($fits($line.$piece)) {
                $line .= $piece;

                continue;
            }

            if ($line !== '') {
                $lines[] = rtrim($line);
            }

            while (mb_strlen(rtrim($piece)) > 1 && ! $fits($piece)) {
                $length = mb_strlen(rtrim($piece)) - 1;

                while ($length > 1 && ! $fits(mb_substr($piece, 0, $length))) {
                    $length--;
                }

                $lines[] = mb_substr($piece, 0, $length);
                $piece = mb_substr($piece, $length);
            }

            $line = $piece;
        }

        if (rtrim($line) !== '') {
            $lines[] = rtrim($line);
        }

        return $lines;
    }

    /**
     * The text, shortened with an ellipsis until it is no wider than $width.
     *
     * @param  callable(string): float  $measure
     */
    private function truncate(string $text, callable $measure, float $width): string
    {
        if ($measure($text) <= $width) {
            return $text;
        }

        $text = rtrim($text, '…');

        while (mb_strlen($text) > 1 && $measure(rtrim($text).'…') > $width) {
            $text = mb_substr($text, 0, -1);
        }

        return rtrim($text).'…';
    }

    /*
     * Drawing. Sizes and positions are in pixels of the final image, scaled
     * up here to the size things are drawn at.
     */

    /**
     * @param  Rgb  $rgb
     */
    private function text(GdImage $image, string $font, float $size, float $x, float $baseline, array $rgb, string $text, float $tracking = 0): void
    {
        if ($tracking === 0.0) {
            imagettftext($image, $this->points($size), 0, (int) round($x * self::SCALE), (int) round($baseline * self::SCALE), $this->color($image, $rgb), $font, $text);

            return;
        }

        // Spaced out one character at a time, which only works for the monospaced font.
        foreach (mb_str_split($text) as $i => $character) {
            $left = $x + $i * ($size * 0.6 + $tracking);
            imagettftext($image, $this->points($size), 0, (int) round($left * self::SCALE), (int) round($baseline * self::SCALE), $this->color($image, $rgb), $font, $character);
        }
    }

    private function textWidth(string $font, float $size, string $text): float
    {
        $box = imagettfbbox($this->points($size), 0, $font, $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];

        return ($box[2] - $box[0]) / self::SCALE;
    }

    /**
     * The width of text in the monospaced font, whose characters are all 0.6em wide.
     */
    private function monoWidth(string $text, float $size, float $tracking = 1.2): float
    {
        $length = mb_strlen($text);

        return $length * $size * 0.6 + max(0, $length - 1) * $tracking;
    }

    /**
     * The shadow of a rounded rectangle, like CSS box-shadow, on an image
     * with nothing drawn on it yet. Each layer is [y offset, blur, spread,
     * opacity]. Worked out every other pixel, since it is blurry anyway.
     *
     * @param  list<array{int, int, int, float}>  $layers
     */
    private function shadow(GdImage $image, float $x, float $y, float $width, float $height, float $radius, array $layers): void
    {
        $step = 2;
        $small = imagecreatetruecolor(max(1, (int) ceil(imagesx($image) / self::SCALE / $step)), max(1, (int) ceil(imagesy($image) / self::SCALE / $step)));
        imagealphablending($small, false);
        imagesavealpha($small, true);

        for ($row = 0; $row < imagesy($small); $row++) {
            for ($column = 0; $column < imagesx($small); $column++) {
                $clear = 1;

                foreach ($layers as [$offset, $blur, $spread, $opacity]) {
                    $distance = self::distance(($column + 0.5) * $step, ($row + 0.5) * $step - $offset, $x - $spread, $y - $spread, $width + 2 * $spread, $height + 2 * $spread, max(0, $radius + $spread));
                    // A logistic curve, close to how a Gaussian blur fades an edge.
                    $clear *= 1 - $opacity / (1 + exp(1.7 * $distance / ($blur / 2)));
                }

                imagesetpixel($small, $column, $row, $this->color($small, [0, 0, 0], 1 - $clear));
            }
        }

        imagealphablending($image, false);
        imagecopyresampled($image, $small, 0, 0, 0, 0, imagesx($image), imagesy($image), imagesx($small), imagesy($small));
        imagealphablending($image, true);
    }

    private function mono(): string
    {
        return resource_path('fonts/JetBrainsMono-Medium.ttf');
    }
}
