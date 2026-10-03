<?php

namespace App\Cms;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Counts what visitors search for on the site, like PostViews counts views:
 * one number per search per day, nothing about the visitor. Each visitor
 * counts once per search per day.
 */
class SiteSearches
{
    /**
     * How many of the most searched queries the overview looks at.
     */
    private const int LOOKED_AT = 50;

    public function record(Request $request, string $query): void
    {
        $query = self::normalize($query);

        if (! $request->isMethod('GET') || $request->user() || Bots::sent($request) || Post::searchTerms($query) === []) {
            return;
        }

        $today = now()->toDateString();
        $key = "searched.{$today}.".md5($query);

        if ($request->hasSession()) {
            if ($request->session()->get($key)) {
                return;
            }

            $request->session()->put($key, true);
        }

        DB::table('searches')->upsert(
            ['date' => $today, 'query' => $query, 'times' => 1],
            ['date', 'query'],
            ['times' => DB::raw('times + 1')],
        );
    }

    /**
     * The most searched queries over the last $days days, split by whether
     * they find any published posts now, with the number of searches.
     *
     * @return array{total: int, found: list<array{query: string, times: int, results: int}>, nothing: list<array{query: string, times: int}>}
     */
    public function overview(int $days = 30, int $limit = 8): array
    {
        $since = now()->subDays($days - 1)->toDateString();

        $queries = DB::table('searches')
            ->where('date', '>=', $since)
            ->groupBy('query')
            ->orderByDesc(DB::raw('sum(times)'))
            ->orderBy('query')
            ->limit(self::LOOKED_AT)
            ->pluck(DB::raw('sum(times)'), 'query');

        $found = [];
        $nothing = [];

        // Counted now, so a search that found nothing moves over once a post answers it.
        foreach ($queries as $query => $times) {
            $results = Post::query()->where('status', PostStatus::Published)->search((string) $query)->count();

            if ($results > 0) {
                $found[] = ['query' => (string) $query, 'times' => (int) $times, 'results' => $results];
            } else {
                $nothing[] = ['query' => (string) $query, 'times' => (int) $times];
            }
        }

        return [
            'total' => (int) DB::table('searches')->where('date', '>=', $since)->sum('times'),
            'found' => array_slice($found, 0, $limit),
            'nothing' => array_slice($nothing, 0, $limit),
        ];
    }

    /**
     * Lowercase with single spaces, so "Pão  de Queijo" and "pão de queijo"
     * count as one search. Accents stay, to show the search as typed.
     */
    public static function normalize(string $query): string
    {
        return Str::limit(Str::lower(Str::squish($query)), 100, '');
    }
}
