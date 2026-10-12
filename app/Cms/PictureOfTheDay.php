<?php

namespace App\Cms;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Wikimedia Commons' picture of the day, for the home page. Fetched by the
 * server once a day and kept, so visitors' browsers only load the picture
 * itself, and a slow or failing Wikimedia never holds up the page for long.
 *
 * @phpstan-type Picture array{caption: string, description: string|null, artist: string|null, license: string|null, license_url: string|null, image: string, width: int, height: int, page: string}
 */
class PictureOfTheDay
{
    private const string FEED = 'https://api.wikimedia.org/feed/v1/wikipedia/en/featured/';

    /**
     * After a failure, how long to wait before asking Wikimedia again.
     */
    private const int RETRY_AFTER = 1800;

    /**
     * Today's picture, or null when there is none or Wikimedia can not be
     * reached.
     *
     * @return Picture|null
     */
    public function today(): ?array
    {
        $date = now('UTC');

        // False for "none, try again later": a null would not be kept.
        $picture = Cache::get($key = 'picture-of-the-day.'.$date->toDateString());

        if ($picture === null) {
            $picture = $this->fetch($date->format('Y/m/d')) ?? false;
            Cache::put($key, $picture, $picture === false ? self::RETRY_AFTER : $date->copy()->endOfDay());
        }

        return $picture ?: null;
    }

    /**
     * @return Picture|null
     */
    private function fetch(string $date): ?array
    {
        try {
            $response = Http::timeout(4)
                // Wikimedia asks for a User-Agent that says who is asking.
                ->withUserAgent(config('app.name').' ('.config('app.url').')')
                ->acceptJson()
                ->get(self::FEED.$date);

            if (! $response->successful()) {
                Log::warning("Could not get the picture of the day: Wikimedia answered {$response->status()}.");

                return null;
            }

            return $this->picture($response->json('image'));
        } catch (Throwable $e) {
            Log::warning("Could not get the picture of the day: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * The parts of the feed's picture the page shows. Only pictures: on
     * days with a video or a sound there is nothing to show.
     *
     * @return Picture|null
     */
    private function picture(mixed $image): ?array
    {
        $thumbnail = is_array($image) ? ($image['thumbnail'] ?? null) : null;

        if (! is_array($image) || ! is_array($thumbnail) || ! is_string($thumbnail['source'] ?? null) || ! is_string($image['file_page'] ?? null)) {
            return null;
        }

        $text = fn (string $path): ?string => ($value = data_get($image, $path)) && is_string($value) && trim($value) !== '' ? trim($value) : null;

        // The thumbnail is wide enough for the dialog (around 960 pixels),
        // and much lighter than the original.
        $source = Str::before($thumbnail['source'], '?');

        return [
            // A short caption when there is one, or the file's name.
            'caption' => $text('structured.captions.en')
                ?? Str::of((string) $text('title'))->after('File:')->beforeLast('.')->replace('_', ' ')->toString(),
            'description' => $text('description.text'),
            'artist' => $text('artist.text'),
            'license' => $text('license.type'),
            'license_url' => $text('license.url'),
            'image' => $source,
            'width' => (int) ($thumbnail['width'] ?? 0),
            'height' => (int) ($thumbnail['height'] ?? 0),
            'page' => $image['file_page'],
        ];
    }
}
