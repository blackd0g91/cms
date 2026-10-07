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
    public function record(Request $request, Post $post): void
    {
        if (! $request->isMethod('GET') || $request->user() || ! $post->isPublished() || Bots::sent($request)) {
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
            // Leaving out posts in the trash.
            ->whereIn('post_id', Post::query()->select('id'))
            ->groupBy('post_id')
            ->orderByDesc(DB::raw('sum(views)'))
            ->limit($limit)
            ->pluck(DB::raw('sum(views)'), 'post_id');

        $posts = Post::query()->with(['template:id,name,handle', 'author:id,name'])->whereKey($totals->keys())->get()->keyBy('id');

        $popular = [];

        foreach ($totals as $id => $views) {
            if ($post = $posts->get($id)) {
                $popular[] = ['post' => $post, 'views' => (int) $views];
            }
        }

        return $popular;
    }

    /**
     * Views of every post per day over the last $days days, with their total
     * and the total of the $days days before, for the dashboard.
     *
     * @return array{daily: list<array{date: string, views: int}>, total: int, previous: int}
     */
    public function overview(int $days = 30): array
    {
        return ['daily' => $this->daily($days), ...$this->totals($days)];
    }

    /**
     * Views of every post over the last $days days and over the $days days
     * before, to compare them.
     *
     * @return array{total: int, previous: int}
     */
    public function totals(int $days): array
    {
        return [
            'total' => $this->total($days),
            'previous' => (int) DB::table('post_views')
                ->whereBetween('date', [now()->subDays(2 * $days - 1)->toDateString(), now()->subDays($days)->toDateString()])
                ->sum('views'),
        ];
    }

    /**
     * A post's views per day over the last $days days, in the last 30 days
     * and of all time, and its best day, for the post editor. Days are
     * shown from when it was published, if that was more recent (a week at
     * least), rather than as a line of zeros before it.
     *
     * @return array{daily: list<array{date: string, views: int}>, last_30_days: int, total: int, best: array{date: string, views: int}|null}
     */
    public function history(Post $post, int $days = 90): array
    {
        $views = DB::table('post_views')->where('post_id', $post->id);
        $best = (clone $views)->orderByDesc('views')->orderByDesc('date')->first(['date', 'views']);

        if ($post->published_at) {
            $published = (int) $post->published_at->startOfDay()->diffInDays(now()->startOfDay()) + 1;
            $days = min($days, max(7, $published));
        }

        return [
            'daily' => $this->daily($days, $post),
            'last_30_days' => $this->forPost($post),
            'total' => (int) $views->sum('views'),
            'best' => $best ? ['date' => (string) $best->date, 'views' => (int) $best->views] : null,
        ];
    }

    /**
     * Views per day over the last $days days, oldest first and ending today,
     * including days without any. Of one post, or of all of them.
     *
     * @return list<array{date: string, views: int}>
     */
    public function daily(int $days = 30, ?Post $post = null): array
    {
        $start = now()->subDays($days - 1);

        $counts = DB::table('post_views')
            ->when($post, fn ($query, Post $post) => $query->where('post_id', $post->id))
            ->where('date', '>=', $start->toDateString())
            ->groupBy('date')
            ->pluck(DB::raw('sum(views)'), 'date');

        return array_map(function (int $offset) use ($start, $counts) {
            $date = $start->addDays($offset)->toDateString();

            return ['date' => $date, 'views' => (int) $counts->get($date, 0)];
        }, range(0, $days - 1));
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
}
