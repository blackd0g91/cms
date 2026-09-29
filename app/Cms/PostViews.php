<?php

namespace App\Cms;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * A privacy-friendly view counter: one number per post per day, nothing
 * about the visitor. Each visitor counts once per post per day, using the
 * session the site already has, so refreshing does not inflate it.
 */
class PostViews
{
    /**
     * User agents of crawlers and link preview fetchers, which are not readers.
     */
    private const string BOTS = '/bot|crawl|spider|slurp|preview|facebookexternalhit|embedly|whatsapp|telegram|discord|slack|skype|curl|wget|python|headless|lighthouse|monitor|uptime/i';

    public function record(Request $request, Post $post): void
    {
        if (! $request->isMethod('GET') || $request->user() || ! $post->isPublished() || $this->isBot($request)) {
            return;
        }

        $today = now()->toDateString();
        $key = "viewed.{$today}.{$post->id}";

        if ($request->hasSession()) {
            if ($request->session()->get($key)) {
                return;
            }

            $request->session()->put($key, true);
        }

        DB::table('post_views')->upsert(
            ['post_id' => $post->id, 'date' => $today, 'views' => 1],
            ['post_id', 'date'],
            ['views' => DB::raw('views + 1')],
        );
    }

    /**
     * The most viewed posts over the last $days days, with their view counts.
     *
     * @return list<array{post: Post, views: int}>
     */
    public function popular(int $days = 30, int $limit = 5): array
    {
        $totals = DB::table('post_views')
            ->where('date', '>=', now()->subDays($days - 1)->toDateString())
            ->groupBy('post_id')
            ->orderByDesc(DB::raw('sum(views)'))
            ->limit($limit)
            ->pluck(DB::raw('sum(views)'), 'post_id');

        $posts = Post::query()->with('template:id,name,handle')->whereKey($totals->keys())->get()->keyBy('id');

        $popular = [];

        foreach ($totals as $id => $views) {
            if ($post = $posts->get($id)) {
                $popular[] = ['post' => $post, 'views' => (int) $views];
            }
        }

        return $popular;
    }

    public function total(int $days = 30): int
    {
        return (int) DB::table('post_views')
            ->where('date', '>=', now()->subDays($days - 1)->toDateString())
            ->sum('views');
    }

    public function forPost(Post $post, int $days = 30): int
    {
        return (int) DB::table('post_views')
            ->where('post_id', $post->id)
            ->where('date', '>=', now()->subDays($days - 1)->toDateString())
            ->sum('views');
    }

    private function isBot(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        return $agent === '' || preg_match(self::BOTS, $agent) === 1;
    }
}
