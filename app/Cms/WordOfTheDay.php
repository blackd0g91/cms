<?php

namespace App\Cms;

use App\Cms\Concerns\FetchesDaily;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Wiktionary's word of the day, for the home page cards: the word, what
 * kind of word it is and its meanings, read from the day's page, where
 * Wiktionary marks them for its own feed.
 *
 * @phpstan-type Word array{word: string, kind: string|null, meanings: list<string>, url: string}
 */
class WordOfTheDay
{
    use FetchesDaily;

    private const string API = 'https://en.wiktionary.org/w/api.php';

    /**
     * How many meanings to keep.
     */
    private const int MEANINGS = 3;

    /**
     * @return Word|null
     */
    public function today(): ?array
    {
        $today = now('UTC');

        return $this->daily('word-of-the-day', function () use ($today) {
            $response = $this->http()->get(self::API, [
                'action' => 'parse',
                'page' => 'Wiktionary:Word_of_the_day/'.$today->format('Y/F_j'),
                'prop' => 'text',
                'format' => 'json',
                'formatversion' => 2,
            ]);

            if (! $response->successful() || ! is_string($html = $response->json('parse.text'))) {
                Log::warning("Could not get the word of the day: Wiktionary answered {$response->status()}.");

                return null;
            }

            return $this->word($html);
        });
    }

    /**
     * @return Word|null
     */
    private function word(string $html): ?array
    {
        $document = new DOMDocument;
        // The page is html, not xml: its warnings are only noise.
        @$document->loadHTML('<?xml encoding="utf-8"?>'.$html);
        $xpath = new DOMXPath($document);

        $word = Str::squish((string) $xpath->evaluate('string(//*[@id="WOTD-rss-title"])'));

        if ($word === '') {
            return null;
        }

        // What kind of word it is follows it, in italics: "adj", "noun"...
        $kind = Str::squish((string) $xpath->evaluate('string((//*[@id="WOTD-rss-title"]/ancestor::b[1]/following-sibling::i)[1])'));

        $meanings = [];

        foreach ($xpath->query('//*[@id="WOTD-rss-description"]//ol/li') ?: [] as $item) {
            if (! $item instanceof DOMElement) {
                continue;
            }

            // Its own examples and sub-lists are left out.
            foreach ($xpath->query('.//dl|.//ul|.//ol', $item) ?: [] as $nested) {
                if ($nested instanceof DOMElement) {
                    $nested->parentNode?->removeChild($nested);
                }
            }

            if (($meaning = Str::squish($item->textContent)) !== '') {
                $meanings[] = $meaning;
            }

            if (count($meanings) === self::MEANINGS) {
                break;
            }
        }

        return [
            'word' => $word,
            'kind' => $kind !== '' ? $kind : null,
            'meanings' => $meanings,
            'url' => 'https://en.wiktionary.org/wiki/'.rawurlencode(str_replace(' ', '_', $word)),
        ];
    }
}
