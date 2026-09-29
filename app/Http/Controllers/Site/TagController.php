<?php

namespace App\Http\Controllers\Site;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Tag;
use Illuminate\View\View;

class TagController extends Controller
{
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

    public function show(Tag $tag): View
    {
        return view('site.tags.show', [
            'tag' => $tag,
            'posts' => $tag->posts()
                ->with(['template', 'thumbnail'])
                ->where('status', PostStatus::Published)
                ->pinnedFirst()
                ->get(),
        ]);
    }
}
