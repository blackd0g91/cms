<?php

namespace App\Cms;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Games given away free to keep right now, from GamerPower
 * (gamerpower.com), for the home page. Fetched by the server and kept for an
 * hour, so visitors' browsers only load the games' pictures, and a slow or
 * failing GamerPower never holds up the page for long.
 *
 * @phpstan-type Game array{title: string, store: string|null, platforms: list<string>, devices: list<string>, worth: string|null, ends_at: string|null, image: string, url: string, description: string|null, claimed: int}
 */
class FreeGames
{
    private const string API = 'https://www.gamerpower.com/api/giveaways?type=game&sort-by=popularity';

    private const int FRESH_FOR = 3600;

    /**
     * After a failure, how long to wait before asking GamerPower again.
     */
    private const int RETRY_AFTER = 900;

    /**
     * The kinds of device a game is for, in the order the filters show
     * them, from the platform names GamerPower uses.
     */
    private const array DEVICES = [
        'PC' => '/\b(pc|steam|epic|gog|itch\.io|drm-free|ubisoft|origin|ea app|battle\.net|stove)\b/i',
        'PlayStation' => '/\b(ps\d|playstation)\b/i',
        'Xbox' => '/\bxbox\b/i',
        'Switch' => '/\bswitch\b/i',
        'Mobile' => '/\b(android|ios)\b/i',
        'VR' => '/\bvr\b/i',
    ];

    /**
     * The games still free right now: those ending soonest first, as they
     * are the ones to not miss, then the rest, most claimed first.
     *
     * @return list<Game>
     */
    public function current(): array
    {
        // False for "none, try again later": a null would not be kept.
        $games = Cache::get('free-games');

        if ($games === null) {
            $games = $this->fetch() ?? false;
            Cache::put('free-games', $games, $games === false ? self::RETRY_AFTER : self::FRESH_FOR);
        }

        // Kept for an hour, so some may have ended since.
        $games = array_values(array_filter(
            $games ?: [],
            fn (array $game) => $game['ends_at'] === null || CarbonImmutable::parse($game['ends_at'])->isFuture(),
        ));

        // A stable sort, so games ending together stay most claimed first.
        usort($games, fn (array $a, array $b) => ($a['ends_at'] ?? '9999') <=> ($b['ends_at'] ?? '9999'));

        return $games;
    }

    /**
     * When a game stops being free, for people: "Ends today", "Ends in 3
     * days" or "No end date".
     *
     * @param  Game  $game
     */
    public static function ending(array $game): string
    {
        if ($game['ends_at'] === null) {
            return 'No end date';
        }

        $days = (int) now()->startOfDay()->diffInDays(CarbonImmutable::parse($game['ends_at'])->startOfDay());

        return match (true) {
            $days <= 0 => 'Ends today',
            $days === 1 => 'Ends tomorrow',
            default => "Ends in {$days} days",
        };
    }

    /**
     * Whether a game stops being free within two days.
     *
     * @param  Game  $game
     */
    public static function endingSoon(array $game): bool
    {
        return $game['ends_at'] !== null && CarbonImmutable::parse($game['ends_at'])->lte(now()->addDays(2));
    }

    /**
     * @return list<Game>|null
     */
    private function fetch(): ?array
    {
        try {
            $response = Http::timeout(4)
                ->withUserAgent(config('app.name').' ('.config('app.url').')')
                ->acceptJson()
                ->get(self::API);

            // No giveaways at all is answered with a 201 and a message.
            if ($response->status() === 201) {
                return [];
            }

            if (! $response->successful() || ! is_array($response->json())) {
                Log::warning("Could not get the free games: GamerPower answered {$response->status()}.");

                return null;
            }

            return array_values(array_filter(array_map($this->game(...), $response->json())));
        } catch (Throwable $e) {
            Log::warning("Could not get the free games: {$e->getMessage()}");

            return null;
        }
    }

    /**
     * The parts of a giveaway the page shows, or null for one that can not
     * be shown.
     *
     * @return Game|null
     */
    private function game(mixed $giveaway): ?array
    {
        if (! is_array($giveaway) || ! is_string($giveaway['title'] ?? null) || ! is_string($giveaway['open_giveaway_url'] ?? null) || ! is_string($giveaway['thumbnail'] ?? null)) {
            return null;
        }

        if (($giveaway['status'] ?? 'Active') !== 'Active') {
            return null;
        }

        // Titles are like "Pony Island (Steam) Giveaway" or "Dwarven Realms
        // (Steam) Key Giveaway": the game, and the store giving it away.
        $title = trim($giveaway['title']);
        $store = null;

        if (preg_match('/^(.+?)\s*\(([^()]+)\)\s*(?:key\s+)?giveaway$/i', $title, $match)) {
            [, $title, $store] = $match;
        } else {
            $title = (string) preg_replace('/\s*(?:key\s+)?giveaway$/i', '', $title);
        }

        $platforms = array_values(array_filter(array_map('trim', explode(',', (string) ($giveaway['platforms'] ?? '')))));
        $known = fn (mixed $value): ?string => is_string($value) && $value !== '' && $value !== 'N/A' ? $value : null;
        $endsAt = $known($giveaway['end_date'] ?? null);

        return [
            'title' => $title,
            'store' => $store,
            'platforms' => $platforms,
            'devices' => array_keys(array_filter(self::DEVICES, fn (string $pattern) => preg_match($pattern, implode(', ', $platforms)) === 1)),
            'worth' => $known($giveaway['worth'] ?? null),
            // GamerPower gives the end in UTC.
            'ends_at' => $endsAt ? CarbonImmutable::parse($endsAt, 'UTC')->toIso8601String() : null,
            'image' => $giveaway['thumbnail'],
            'url' => $giveaway['open_giveaway_url'],
            'description' => $known($giveaway['description'] ?? null),
            'claimed' => (int) ($giveaway['users'] ?? 0),
        ];
    }
}
