<?php

namespace App\Models;

use App\Cms\ImageVariants;
use App\Cms\Svg;
use App\Cms\Trash;
use Carbon\CarbonImmutable;
use Database\Factories\MediaFactory;
use Illuminate\Database\Eloquent\Attributes\Appends;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * An uploaded image.
 *
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property string $filename
 * @property string $mime_type
 * @property int $size
 * @property int|null $width
 * @property int|null $height
 * @property array<string, string>|null $variants Resized copies, keyed by width
 * @property string|null $alt
 * @property-read string $url
 * @property-read string $thumb_url A small version, for grids and pickers
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at When it was moved to the trash
 */
#[Fillable(['disk', 'path', 'filename', 'mime_type', 'size', 'width', 'height', 'alt'])]
#[Appends(['url', 'thumb_url'])]
class Media extends Model
{
    /** @use HasFactory<MediaFactory> */
    use HasFactory, Prunable, SoftDeletes;

    protected static function booted(): void
    {
        // In the trash the files stay, so the image can be restored.
        static::forceDeleted(function (Media $media) {
            app(ImageVariants::class)->deleteFiles($media);
            Storage::disk($media->disk)->delete($media->path);
        });
    }

    /**
     * Images in the trash for long enough to be deleted for good.
     *
     * @return Builder<Media>
     */
    public function prunable(): Builder
    {
        return static::onlyTrashed()->where('deleted_at', '<=', now()->subDays(Trash::DAYS));
    }

    /**
     * Store an uploaded image on the public disk. SVGs are sanitized first,
     * and only the cleaned version is kept.
     *
     * @throws ValidationException when an SVG cannot be read
     */
    public static function upload(UploadedFile $file): self
    {
        if ($file->getMimeType() === 'image/svg+xml') {
            return self::uploadSvg($file);
        }

        $path = $file->store('media', 'public');
        $dimensions = @getimagesize($file->getRealPath()) ?: [null, null];

        $media = self::create([
            'disk' => 'public',
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'width' => $dimensions[0],
            'height' => $dimensions[1],
        ]);

        app(ImageVariants::class)->generate($media);

        return $media;
    }

    /**
     * The URL of the smallest version at least $width pixels wide.
     */
    public function urlFor(int $width): string
    {
        foreach ($this->variants ?? [] as $variantWidth => $path) {
            if ((int) $variantWidth >= $width) {
                return Storage::disk($this->disk)->url($path);
            }
        }

        return $this->url;
    }

    /**
     * Every version with its width, for an <img srcset>. Empty when there
     * are no resized copies.
     */
    public function srcset(): string
    {
        if (! $this->variants) {
            return '';
        }

        $sources = collect($this->variants)
            ->map(fn (string $path, string|int $width) => Storage::disk($this->disk)->url($path)." {$width}w");

        if ($this->width) {
            $sources->push("{$this->url} {$this->width}w");
        }

        return $sources->implode(', ');
    }

    public function isSvg(): bool
    {
        return $this->mime_type === 'image/svg+xml';
    }

    private static function uploadSvg(UploadedFile $file): self
    {
        $svg = app(Svg::class);
        $clean = $svg->sanitize((string) file_get_contents($file->getRealPath()));

        if ($clean === null) {
            throw ValidationException::withMessages([
                'file' => 'This SVG could not be read. Try exporting it again from your editor.',
            ]);
        }

        $path = 'media/'.Str::random(40).'.svg';
        Storage::disk('public')->put($path, $clean);
        [$width, $height] = $svg->dimensions($clean);

        return self::create([
            'disk' => 'public',
            'path' => $path,
            'filename' => $file->getClientOriginalName(),
            'mime_type' => 'image/svg+xml',
            'size' => strlen($clean),
            'width' => $width,
            'height' => $height,
        ]);
    }

    /**
     * @return Attribute<string, never>
     */
    protected function url(): Attribute
    {
        return Attribute::get(fn () => Storage::disk($this->disk)->url($this->path));
    }

    /**
     * @return Attribute<string, never>
     */
    protected function thumbUrl(): Attribute
    {
        return Attribute::get(fn () => $this->urlFor(400));
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'variants' => 'array',
        ];
    }
}
