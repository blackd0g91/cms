<?php

namespace App\Http\Controllers\Site;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Show the most recently published posts across all templates.
     */
    public function __invoke(): View
    {
        return view('site.home', [
            'posts' => Post::query()
                ->with('template')
                ->where('status', PostStatus::Published)
                ->latest('published_at')
                ->limit(20)
                ->get(),
        ]);
    }
}
