<?php

namespace App\Http\Controllers\Site;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\View\View;

class TagController extends Controller
{
    /**
     * Whole rows of the grid, with two or three posts in a row.
     */
    private const int PER_PAGE = 24;

    /**
     * Every tag that has published posts, with how many.
     */
    public function index(): View
    {
        return view('site.tags.index', [
            'tags' => Tag::query()
                ->whereHas('posts', fn ($query) => $query->where('status', PostStatus::Published))
                ->withCount(['posts' => fn ($query) => $query->where('status', PostStatus::Published)])
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * A tag's published posts, a page at a time.
     */
    public function show(Tag $tag): View
    {
        $posts = $tag->posts()
            ->with(['template', 'thumbnail'])
            ->where('status', PostStatus::Published)
            ->pinnedFirst()
            ->paginate(self::PER_PAGE);

        // Past the last page there is nothing to show.
        abort_if($posts->isEmpty() && $posts->currentPage() > 1, 404);

        return view('site.tags.show', [
            'tag' => $tag,
            'posts' => $posts,
        ]);
    }
}
