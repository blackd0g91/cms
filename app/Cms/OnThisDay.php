<?php

namespace App\Cms;

use App\Cms\Concerns\FetchesDaily;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * What happened on this day in years past, as Wikipedia picks it, for the
 * home page cards: a handful of events, the latest first, each with the
 * article it is about.
 *
 * @phpstan-type Event array{year: int, text: string, title: string|null, url: string|null, image: string|null}
 */
class OnThisDay
{
    use FetchesDaily;

    private const string FEED = 'https://api.wikimedia.org/feed/v1/wikipedia/en/onthisday/selected/';

    /**
     * How many events to keep.
     */
    private const int EVENTS = 8;

    /**
     * Today's events, or null when Wikipedia can not be reached.
     *
     * @return array{date: string, events: list<Event>}|null
     */
    public function today(): ?array
    {
        $today = now('UTC');

        return $this->daily('on-this-day', function () use ($today) {
            $response = $this->http()->get(self::FEED.$today->format('m/d'));

            if (! $response->successful()) {
                Log::warning("Could not get on this day: Wikimedia answered {$response->status()}.");

                return null;
            }

            $events = array_values(collect((array) $response->json('selected'))
                ->filter(fn (mixed $event) => is_array($event) && is_string($event['text'] ?? null) && is_int($event['year'] ?? null))
                ->map(function (array $event) {
                    $page = collect((array) ($event['pages'] ?? []))->first(fn (mixed $page) => is_array($page));

                    $text = fn (string $path): ?string => is_array($page) && is_string($value = data_get($page, $path)) && $value !== '' ? $value : null;

                    return [
                        'year' => $event['year'],
                        'text' => trim($event['text']),
                        'title' => $text('titles.normalized'),
                        'url' => $text('content_urls.desktop.page'),
                        'image' => ($source = $text('thumbnail.source')) !== null ? Str::before($source, '?') : null,
                    ];
                })
                ->sortByDesc('year')
                ->take(self::EVENTS)
                ->all());

            return $events === [] ? null : ['date' => $today->format('F j'), 'events' => $events];
        });
    }
}
