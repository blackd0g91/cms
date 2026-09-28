<?php

namespace App\Http\Controllers\Site;

use App\Cms\LayoutRenderer;
use App\Cms\Settings;
use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Models\Template;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Response;

/**
 * RSS feeds of published posts, for the whole site and per template.
 */
class FeedController extends Controller
{
    public function __construct(
        private Settings $settings,
        private LayoutRenderer $renderer,
    ) {}

    public function index(): Response
    {
        return $this->feed(
            Post::query(),
            title: $this->settings->siteName(),
            description: $this->settings->get('tagline'),
            link: route('home'),
            self: route('feed'),
        );
    }

    public function template(Template $template): Response
    {
        return $this->feed(
            $template->posts()->getQuery(),
            title: "{$template->name} - {$this->settings->siteName()}",
            description: $template->description,
            link: route('site.template', $template),
            self: route('site.template.feed', $template),
        );
    }

    /**
     * @param  Builder<Post>  $query
     */
    private function feed(Builder $query, string $title, mixed $description, string $link, string $self): Response
    {
        $posts = $query
            ->with(['template', 'thumbnail'])
            ->where('status', PostStatus::Published)
            ->latest('published_at')
            ->limit(30)
            ->get();

        return response()
            ->view('site.xml.feed', [
                'title' => $title,
                'description' => is_string($description) && $description !== '' ? $description : $title,
                'link' => $link,
                'self' => $self,
                'posts' => $posts,
                'render' => fn (Post $post) => $this->renderer->render($post),
            ])
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
