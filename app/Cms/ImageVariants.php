<?php

namespace App\Cms;

use App\Models\Media;
use GdImage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Makes smaller WebP copies of uploaded images, so pages can let the browser
 * pick the smallest one that still looks sharp (see Media::srcset()).
 *
 * SVGs scale on their own and GIFs would lose their animation, so both are
 * left as they are.
 */
class ImageVariants
{
    /**
     * Widths to generate, in pixels. Only those smaller than the original.
     */
    public const array WIDTHS = [400, 800, 1600];

    private const int QUALITY = 80;

    private const array RESIZABLE = ['image/jpeg', 'image/png', 'image/webp', 'image/avif'];

    public function supports(Media $media): bool
    {
        return in_array($media->mime_type, self::RESIZABLE, true) && function_exists('imagewebp');
    }

    /**
     * Create the variants for an image, replacing any it already had, and
     * correct its stored size for EXIF rotation. Failures are logged rather
     * than thrown: the original image always keeps working.
     */
    public function generate(Media $media): void
    {
        if (! $this->supports($media)) {
            return;
        }

        $disk = Storage::disk($media->disk);

        try {
            // Large photos need a lot of memory while decoded.
            ini_set('memory_limit', '512M');

            $image = $this->load($disk->path($media->path), $media->mime_type);
            $width = imagesx($image);
            $height = imagesy($image);

            $this->deleteFiles($media);
            $variants = [];

            foreach (self::WIDTHS as $target) {
                if ($target >= $width) {
                    break;
                }

                $resized = imagescale($image, $target, (int) round($height * $target / $width), IMG_BICUBIC);

                if ($resized === false) {
                    continue;
                }

                imagesavealpha($resized, true);
                $path = sprintf('media/variants/%s-%d.webp', pathinfo($media->path, PATHINFO_FILENAME), $target);

                ob_start();
                imagewebp($resized, null, self::QUALITY);
                $disk->put($path, (string) ob_get_clean());

                $variants[(string) $target] = $path;
            }

            $media->forceFill([
                'variants' => $variants ?: null,
                'width' => $width,
                'height' => $height,
            ])->save();
        } catch (Throwable $e) {
            Log::warning("Could not create image variants for media {$media->id}: {$e->getMessage()}");
        }
    }

    public function deleteFiles(Media $media): void
    {
        if ($media->variants) {
            Storage::disk($media->disk)->delete(array_values($media->variants));
        }
    }

    /**
     * Decode the image, turned upright if the camera recorded a rotation.
     */
    private function load(string $file, string $mime): GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($file),
            'image/png' => imagecreatefrompng($file),
            'image/webp' => imagecreatefromwebp($file),
            'image/avif' => imagecreatefromavif($file),
            default => false,
        };

        if ($image === false) {
            throw new \RuntimeException('The image could not be decoded.');
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $mime === 'image/jpeg' ? $this->orient($image, $file) : $image;
    }

    private function orient(GdImage $image, string $file): GdImage
    {
        $exif = function_exists('exif_read_data') ? @exif_read_data($file) : false;
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        $angle = match ($orientation) {
            3, 4 => 180,
            5, 6 => 270,
            7, 8 => 90,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }
}
