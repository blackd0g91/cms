<?php

namespace App\Cms;

use App\Models\Link;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Counts clicks on the sidebar links and the profile icons, like PostViews
 * counts views: one number per link per day, nothing about the visitor. Each
 * visitor counts once per link per day.
 */
class LinkClicks
{
    public function __construct(private Settings $settings) {}

    /**
     * Count a click on a target ("link:{id}" or "profile:{site}"), if it is
     * one of the links the site shows now.
     */
    public function record(Request $request, string $target): void
    {
        if ($request->user() || Bots::sent($request) || ! array_key_exists($target, $this->targets())) {
            return;
        }

        $today = now()->toDateString();
        $key = "clicked.{$today}.{$target}";

        if ($request->hasSession()) {
            if ($request->session()->get($key)) {
                return;
            }

            $request->session()->put($key, true);
        }

        DB::table('link_clicks')->upsert(
            ['date' => $today, 'target' => $target, 'clicks' => 1],
            ['date', 'target'],
            ['clicks' => DB::raw('clicks + 1')],
        );
    }

    /**
     * Every link and profile the site shows, most clicked first over the last
     * $days days, those never clicked included.
     *
     * @return array{total: int, links: list<array{target: string, kind: string, label: string, emoji: string|null, icon: string|null, href: string, clicks: int}>}
     */
    public function overview(int $days = 30): array
    {
        $counts = DB::table('link_clicks')
            ->where('date', '>=', now()->subDays($days - 1)->toDateString())
            ->groupBy('target')
            ->pluck(DB::raw('sum(clicks)'), 'target');

        $links = [];

        foreach ($this->targets() as $target => $link) {
            $links[] = [...$link, 'target' => $target, 'clicks' => (int) $counts->get($target, 0)];
        }

        // Stable, so links with as many clicks keep the order the site shows them in.
        usort($links, fn (array $a, array $b) => $b['clicks'] <=> $a['clicks']);

        return [
            'total' => array_sum(array_column($links, 'clicks')),
            'links' => $links,
        ];
    }

    /**
     * The links the site shows now, by target, in the order it shows them:
     * the sidebar links, then the profiles.
     *
     * @return array<string, array{kind: string, label: string, emoji: string|null, icon: string|null, href: string}>
     */
    private function targets(): array
    {
        $targets = [];

        foreach (Link::query()->with('post.template')->orderBy('position')->get() as $link) {
            if (($href = $link->href()) !== null) {
                $targets["link:{$link->id}"] = ['kind' => 'link', 'label' => $link->text(), 'emoji' => $link->emoji, 'icon' => null, 'href' => $href];
            }
        }

        foreach ($this->settings->profiles() as ['site' => $site, 'href' => $href]) {
            $targets["profile:{$site->value}"] = ['kind' => 'profile', 'label' => $site->label(), 'emoji' => null, 'icon' => $site->icon(), 'href' => $href];
        }

        return $targets;
    }
}
