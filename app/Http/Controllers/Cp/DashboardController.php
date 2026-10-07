<?php

namespace App\Http\Controllers\Cp;

use App\Cms\PostViews;
use App\Cms\SiteSearches;
use App\Cms\SystemStatus;
use App\Cms\Trash;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Template;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request, PostViews $views, SystemStatus $system, SiteSearches $searches, Trash $trash): Response
    {
        $trash->purgeExpired();
        $user = $request->user();
        $top = $views->popular(7, 1)[0] ?? null;
        $lastPublished = Post::query()
            ->with(['template:id,name,handle', 'author:id,name'])
            ->where('status', PostStatus::Published)
            ->latest('published_at')
            ->first();

        return Inertia::render('cp/Dashboard', [
            // The details are on the health page, for admins, who can do something about them.
            'systemWarnings' => $user?->isAdmin() ? count($system->report()['warnings']) : 0,
            'week' => [
                'views' => $views->totals(7),
                'searches' => $searches->totals(7),
                'top' => $top ? [...$top['post']->toListItem(), 'views' => $top['views']] : null,
                'lastPublished' => $lastPublished?->toListItem(),
            ],
            // Yours, and those from before posts had authors.
            'drafts' => Post::query()
                ->with(['template:id,name,handle', 'author:id,name'])
                ->where('status', PostStatus::Draft)
                ->where(fn (Builder $query) => $query->whereNull('author_id')->orWhere('author_id', $user?->id))
                ->latest('updated_at')
                ->limit(5)
                ->get()
                ->map(fn (Post $post) => [
                    ...$post->toListItem(),
                    'description' => $post->description(160),
                    'reading_minutes' => $post->readingMinutes(),
                ]),
            'views' => $views->overview(),
            'popular' => array_map(fn (array $row) => [
                ...$row['post']->toListItem(),
                'views' => $row['views'],
            ], $views->popular()),
            'searches' => $searches->overview(),
            'activity' => $this->activity(),
            'templates' => Template::query()
                ->orderBy('name')
                ->get(['id', 'name', 'handle', 'color'])
                ->map(fn (Template $template) => [
                    ...$template->only(['id', 'name']),
                    'accent' => $template->accentColor(),
                ]),
            'recentPosts' => $this->posts(Post::query()->latest('updated_at')->limit(8)),
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
            ->with(['template:id,name,handle', 'author:id,name'])
            ->get(['id', 'template_id', 'author_id', 'title', 'slug', 'status', 'published_at', 'pinned_at', 'updated_at'])
            ->map(fn (Post $post) => $post->toListItem())
            ->all();
    }
}
