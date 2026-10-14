<?php

namespace App\Cms;

use App\Cms\Concerns\FetchesDaily;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;

/**
 * NASA's astronomy picture of the day, for the home page cards: the
 * picture (or, some days, a video), its title and NASA's explanation. Its
 * free "DEMO_KEY" is enough for one request a day; a key of the site's own
 * can be set with NASA_API_KEY.
 *
 * @phpstan-type Apod array{title: string, explanation: string|null, image: string|null, video: string|null, credit: string|null, page: string}
 */
class AstronomyPicture
{
    use FetchesDaily;

    /**
     * NASA's API is sometimes slow (7 seconds and more), and is only asked
     * once a day.
     */
    protected function timeout(): int
    {
        return 10;
    }

    private const string API = 'https://api.nasa.gov/planetary/apod';

    /**
     * @return Apod|null
     */
    public function today(): ?array
    {
        return $this->daily('astronomy-picture', function () {
            $response = $this->http()
                // It sometimes answers 500 for no reason, and is fine a moment later.
                ->retry(2, 1000, fn ($exception) => $exception instanceof RequestException && $exception->response->serverError(), throw: false)
                ->get(self::API, ['api_key' => config('services.nasa.key') ?: 'DEMO_KEY']);

            if (! $response->successful() || ! is_string($title = $response->json('title'))) {
                Log::warning("Could not get NASA's picture of the day: it answered {$response->status()}.");

                return null;
            }

            $isImage = $response->json('media_type') === 'image';
            $url = $response->json('url');
            $date = (string) $response->json('date');

            return [
                'title' => trim($title),
                'explanation' => is_string($text = $response->json('explanation')) ? trim($text) : null,
                'image' => $isImage && is_string($url) ? $url : null,
                'video' => ! $isImage && is_string($url) ? $url : null,
                'credit' => is_string($credit = $response->json('copyright')) ? trim(preg_replace('/\s+/', ' ', $credit) ?? '') : null,
                // NASA's own page for the day, like apod.nasa.gov/apod/ap261014.html.
                'page' => preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)
                    ? 'https://apod.nasa.gov/apod/ap'.substr(str_replace('-', '', $date), 2).'.html'
                    : 'https://apod.nasa.gov/apod/',
            ];
        });
    }
}
