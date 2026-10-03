<?php

namespace App\Cms;

use Illuminate\Http\Request;

/**
 * Crawlers and link preview fetchers, which are not readers, so their visits
 * and searches are not counted.
 */
class Bots
{
    private const string USER_AGENTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|whatsapp|telegram|discord|slack|skype|curl|wget|python|headless|lighthouse|monitor|uptime/i';

    public static function sent(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        return $agent === '' || preg_match(self::USER_AGENTS, $agent) === 1;
    }
}
