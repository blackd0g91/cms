<?php

namespace App\Cms;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Counts where visitors come from, like PostViews counts views: one number
 * per source per day, nothing about the visitor. A source is the site that
 * linked here, without "www." and the like, or the name in a ?ref= or
 * ?utm_source= parameter, which links from your own apps can carry. Visits
 * without either count as direct. Each visitor counts once per source per
 * day (see DailyVisitor), when arriving: moving around the site does not count.
 */
class Referrers
{
    /**
     * Prefixes that are the same site, like "m.facebook.com" for facebook.com.
     */
    private const string SAME_SITE = '/^(www\d*|m|l|lm|mobile)\./';

    /**
     * Sources one address can add to the counts in a day.
     */
    private const int SOURCES_PER_ADDRESS = 10;

    public function record(Request $request): void
    {
        if ($request->user() || Bots::sent($request)) {
            return;
        }

        $source = self::source($request);

        // Following a link within the site.
        if ($source === null) {
            return;
        }

        if (! DailyVisitor::first($request, 'referred.'.md5($source))) {
            return;
        }

        // Each source counted for an address is a new row on the dashboard,
        // so one address can not make up more than a few in a day.
        if (! DailyVisitor::withinLimit($request, 'referred.sources.'.now()->toDateString(), self::SOURCES_PER_ADDRESS)) {
            return;
        }

        DB::table('referrers')->upsert(
            ['date' => now()->toDateString(), 'source' => $source, 'visits' => 1],
            ['date', 'source'],
            ['visits' => DB::raw('visits + 1')],
        );
    }

    /**
     * Where a request comes from: a name given in the address, the other
     * site's host, "" for none, or null for a page of this site.
     */
    public static function source(Request $request): ?string
    {
        $named = $request->query('ref') ?? $request->query('utm_source');

        if (is_string($named) && ($named = self::name($named)) !== '') {
            return $named;
        }

        $referrer = (string) $request->headers->get('referer');

        if ($referrer === '') {
            return '';
        }

        $host = Str::lower((string) parse_url($referrer, PHP_URL_HOST));

        if ($host === '') {
            return '';
        }

        if ($host === $request->getHost()) {
            return null;
        }

        return Str::limit((string) preg_replace(self::SAME_SITE, '', $host), 100, '');
    }

    /**
     * A name from the address as a plain label: letters, digits, spaces,
     * "-" and "_". Anything else, dots included, becomes a space, so a name
     * can never pass for a site (the dashboard links those).
     */
    private static function name(string $name): string
    {
        $name = (string) preg_replace('/[^\p{L}\p{N} _-]+/u', ' ', $name);

        return Str::limit(Str::lower(Str::squish($name)), 40, '');
    }

    /**
     * The sources visitors came from most over the last $days days, with how
     * many came from each and in all.
     *
     * @return array{total: int, sources: list<array{source: string, visits: int}>}
     */
    public function overview(int $days = 30, int $limit = 8): array
    {
        $since = now()->subDays($days - 1)->toDateString();

        $sources = DB::table('referrers')
            ->where('date', '>=', $since)
            ->groupBy('source')
            ->orderByDesc(DB::raw('sum(visits)'))
            ->orderBy('source')
            ->limit($limit)
            ->pluck(DB::raw('sum(visits)'), 'source');

        $list = [];

        foreach ($sources as $source => $visits) {
            $list[] = ['source' => (string) $source, 'visits' => (int) $visits];
        }

        return [
            'total' => (int) DB::table('referrers')->where('date', '>=', $since)->sum('visits'),
            'sources' => $list,
        ];
    }
}
