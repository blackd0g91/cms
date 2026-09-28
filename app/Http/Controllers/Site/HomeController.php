<?php

namespace App\Http\Controllers\Site;

use App\Cms\Markdown;
use App\Cms\Settings;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Show the most recently published posts across all templates.
     */
    public function __invoke(Settings $settings, Markdown $markdown): View
    {
        $intro = $settings->get('home_intro');

        return view('site.home', [
            'intro' => is_string($intro) && $intro !== '' ? new HtmlString($markdown->render($intro)) : null,
            'posts' => Post::query()
                ->with(['template', 'thumbnail'])
                ->where('status', PostStatus::Published)
                ->latest('published_at')
                ->limit(20)
                ->get(),
        ]);
    }
}
