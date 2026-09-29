<?php

namespace App\Http\Controllers\Cp;

use App\Cms\ContentCheckup;
use App\Cms\PostViews;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Media;
use App\Models\Post;
use App\Models\Template;
use Illuminate\Database\Eloquent\Builder;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(ContentCheckup $checkup, PostViews $views): Response
    {
        return Inertia::render('cp/Dashboard', [
            'popular' => [
                'total' => $views->total(),
                'posts' => array_map(fn (array $row) => [
                    ...$row['post']->toListItem(),
                    'views' => $row['views'],
                ], $views->popular()),
            ],
            'checkup' => $checkup->run(),
            'activity' => $this->activity(),
            'stats' => [
                'published' => Post::query()->where('status', PostStatus::Published)->count(),
                'drafts' => Post::query()->where('status', PostStatus::Draft)->count(),
                'templates' => Template::query()->count(),
                'media' => Media::query()->count(),
            ],
            'templates' => Template::query()
                ->withCount([
                    'posts',
                    'posts as drafts_count' => fn ($query) => $query->where('status', PostStatus::Draft),
                ])
                ->orderBy('name')
                ->get(['id', 'name', 'handle', 'color'])
                ->map(fn (Template $template) => [
                    ...$template->only(['id', 'name', 'handle', 'posts_count', 'drafts_count']),
                    'accent' => $template->accentColor(),
                ]),
            'recentPosts' => $this->posts(Post::query()->latest('updated_at')->limit(8)),
            'drafts' => $this->posts(
                Post::query()->where('status', PostStatus::Draft)->latest('updated_at')->limit(8),
            ),
        ]);
    }

    /**
     * Published posts per month over the last 12 months, oldest first,
     * including months with none.
     *
     * @return list<array{month: string, count: int}>
     */
    private function activity(): array
    {
        $start = now()->startOfMonth()->subMonths(11);

        $counts = Post::query()
            ->where('status', PostStatus::Published)
            ->where('published_at', '>=', $start)
            ->pluck('published_at')
            ->countBy(fn ($date) => $date->format('Y-m'));

        return array_map(function (int $offset) use ($start, $counts) {
            $month = $start->addMonths($offset)->format('Y-m');

            return ['month' => $month, 'count' => (int) $counts->get($month, 0)];
        }, range(0, 11));
    }

    /**
     * @param  Builder<Post>  $query
     * @return array<int, array<string, mixed>>
     */
    private function posts(Builder $query): array
    {
        return $query
            ->with('template:id,name,handle')
            ->get(['id', 'template_id', 'title', 'slug', 'status', 'published_at', 'pinned_at', 'updated_at'])
            ->map(fn (Post $post) => $post->toListItem())
            ->all();
    }
}
