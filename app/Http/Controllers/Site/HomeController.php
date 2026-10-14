<?php

namespace App\Http\Controllers\Site;

use App\Cms\HomeWidgets;
use App\Cms\Markdown;
use App\Cms\Settings;
use App\Enums\HomeWidget;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Project;
use App\Models\Template;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Show the most recently published posts across all templates.
     */
    public function __invoke(Settings $settings, Markdown $markdown, HomeWidgets $widgets): View
    {
        $intro = $settings->get('home_intro');

        return view('site.home', [
            // The usual cards, for everyone: members have theirs on their
            // own page (see MeController).
            ...$widgets->data(HomeWidget::defaults()),
            'projects' => Project::query()->with(['logo', 'background'])->orderBy('position')->get(),
            'intro' => is_string($intro) && $intro !== '' ? new HtmlString($markdown->render($intro)) : null,
            'posts' => Post::query()
                ->with(['template', 'thumbnail'])
                ->where('status', PostStatus::Published)
                ->pinnedFirst()
                ->limit(20)
                ->get(),
            // Their colors are behind the intro.
            'templates' => Template::query()
                ->whereHas('posts', fn (Builder $query) => $query->where('status', PostStatus::Published))
                ->orderBy('name')
                ->get(),
        ]);
    }
}
