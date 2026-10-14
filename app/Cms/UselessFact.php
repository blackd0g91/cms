<?php

namespace App\Cms;

use App\Cms\Concerns\FetchesDaily;
use Illuminate\Support\Facades\Log;

/**
 * A useless fact, a new one each day (uselessfacts.jsph.pl), for the home
 * page cards.
 *
 * @phpstan-type Fact array{text: string, source: string|null, url: string|null}
 */
class UselessFact
{
    use FetchesDaily;

    private const string API = 'https://uselessfacts.jsph.pl/api/v2/facts/today';

    /**
     * @return Fact|null
     */
    public function today(): ?array
    {
        return $this->daily('useless-fact', function () {
            $response = $this->http()->get(self::API, ['language' => 'en']);

            if (! $response->successful() || ! is_string($text = $response->json('text')) || trim($text) === '') {
                Log::warning("Could not get the useless fact: it answered {$response->status()}.");

                return null;
            }

            return [
                'text' => trim($text),
                'source' => is_string($source = $response->json('source')) && $source !== '' ? $source : null,
                'url' => is_string($url = $response->json('source_url')) && str_starts_with($url, 'http') ? $url : null,
            ];
        });
    }
}
