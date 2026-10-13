<?php

namespace App\Http\Controllers\Site;

use App\Cms\FreeGames;
use App\Cms\Markdown;
use App\Cms\PictureOfTheDay;
use App\Cms\Settings;
use App\Enums\HomeWidget;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Template;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;

class HomeController extends Controller
{
    /**
     * Show the most recently published posts across all templates.
     */
    public function __invoke(Request $request, Settings $settings, Markdown $markdown, PictureOfTheDay $picture, FreeGames $games): View
    {
        $intro = $settings->get('home_intro');

        // The cards at the top, the member's own or the defaults. Only those
        // are fetched.
        $widgets = $request->user()?->homeWidgets() ?? HomeWidget::defaults();

        return view('site.home', [
            'intro' => is_string($intro) && $intro !== '' ? new HtmlString($markdown->render($intro)) : null,
            'posts' => Post::query()
                ->with(['template', 'thumbnail'])
                ->where('status', PostStatus::Published)
                ->pinnedFirst()
                ->limit(20)
                ->get(),
            'widgets' => $widgets,
            'picture' => in_array(HomeWidget::PictureOfTheDay, $widgets, true) ? $picture->today() : null,
            'games' => in_array(HomeWidget::FreeGames, $widgets, true) ? $games->current() : [],
            // Their colors are behind the intro.
            'templates' => Template::query()
                ->whereHas('posts', fn (Builder $query) => $query->where('status', PostStatus::Published))
                ->orderBy('name')
                ->get(),
        ]);
    }
}
