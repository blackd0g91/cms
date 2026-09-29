<?php

namespace App\Http\Controllers\Cp;

use App\Cms\ContentCheckup;
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
    public function __invoke(ContentCheckup $checkup): Response
    {
        return Inertia::render('cp/Dashboard', [
            'checkup' => $checkup->run(),
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
